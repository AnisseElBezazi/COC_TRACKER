<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client de l'API Clash of Clans (proxy sans clé). Réessaie tout seul si l'API répond 429.
 *
 * Hors ligne : si COC_API_URL est un dossier (ex. « fixtures/api ») et non une adresse http(s), les réponses sont lues
 * dans des fichiers JSON de ce dossier, ce qui permet de tester sans le serveur (voir fixtures/api/LISEZ-MOI.md).
 */
class CocApi
{
    private HttpClientInterface $client;

    private string $baseUrl;

    public function __construct(
        #[Autowire(env: 'default::COC_API_URL')]
        ?string $baseUrl = null,
        ?HttpClientInterface $client = null,
        // Réponses gardées quelques minutes : revenir sur un joueur ou un groupe ne refait pas les mêmes appels (null : pas de cache).
        #[Autowire(service: 'cache.coc_api')]
        private ?CacheItemPoolInterface $cache = null,
        #[Autowire('%kernel.project_dir%')]
        ?string $projet = null,
    ) {
        if ($baseUrl && !preg_match('#^https?://#i', $baseUrl)) {
            // Mode hors ligne : un dossier de réponses JSON, pas de réseau, pas de cache.
            $dossier = str_starts_with($baseUrl, '/') ? $baseUrl : rtrim((string) $projet, '/').'/'.$baseUrl;
            $this->baseUrl = 'http://fixtures.local/';
            $this->cache = null;
            $client = new MockHttpClient(fn (string $methode, string $url) => $this->reponseLocale(rtrim($dossier, '/'), $url));
            $baseUrl = $this->baseUrl;
        }
        $this->baseUrl = rtrim($baseUrl ?: 'https://api.quentindeglas.com/v1', '/').'/';
        $this->client = $client ?? new RetryableHttpClient(
            // Plusieurs requêtes en parallèle vers le même serveur : l'import d'un groupe en dépend.
            HttpClient::create(['timeout' => 30, 'max_host_connections' => 12]),
            new GenericRetryStrategy([429, 503], delayMs: 2000),
            maxRetries: 4,
        );
    }

    /**
     * Réponse lue dans un fichier du dossier hors ligne : « players/%2382U22QLL/battlelog » -> players__82U22QLL__battlelog.json.
     * Un fichier absent répond 404, comme l'API pour un joueur ou un groupe inconnu.
     */
    private function reponseLocale(string $dossier, string $url): MockResponse
    {
        $chemin = ltrim((string) parse_url($url, \PHP_URL_PATH), '/');
        $fichier = $dossier.'/'.str_replace('/', '__', str_replace('#', '', urldecode($chemin))).'.json';
        $enTetes = ['response_headers' => ['content-type: application/json']];

        return is_file($fichier)
            ? new MockResponse((string) file_get_contents($fichier), $enTetes)
            : new MockResponse('{"reason":"notFound"}', ['http_code' => 404] + $enTetes);
    }

    /** @return array<string, mixed> */
    public function joueur(string $tag): array
    {
        return $this->get('players/'.rawurlencode($tag));
    }

    /** @return list<array<string, mixed>> */
    public function battlelog(string $tag): array
    {
        return $this->get('players/'.rawurlencode($tag).'/battlelog')['items'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function historique(string $tag): array
    {
        return $this->get('players/'.rawurlencode($tag).'/leaguehistory')['items'] ?? [];
    }

    /**
     * Lance d'un coup les requêtes d'un joueur sans attendre leur réponse : elles partent en parallèle,
     * et le résultat se lit ensuite avec toArray(). Appeler cette méthode pour tout un paquet de joueurs
     * AVANT de lire la première réponse est ce qui rend l'import rapide.
     *
     * Avec $complet = false, seuls les combats sont demandés (1 appel au lieu de 3) : suffisant pour un membre
     * de groupe, dont le nom, le clan et les trophées viennent déjà du groupe et l'hôtel de ville de ses adversaires.
     *
     * @return array{profil?: ResponseInterface, historique?: ResponseInterface, battlelog: ResponseInterface}
     */
    public function demanderJoueur(string $tag, bool $complet = true): array
    {
        $joueur = 'players/'.rawurlencode($tag);
        $reponses = ['battlelog' => $this->requete($joueur.'/battlelog')];
        if ($complet) {
            $reponses = ['profil' => $this->requete($joueur), 'historique' => $this->requete($joueur.'/leaguehistory')] + $reponses;
        }

        return $reponses;
    }

    /** @return array<string, mixed> */
    public function groupe(string $groupeTag, int $saisonId, string $joueurTag): array
    {
        return $this->get('leaguegroup/'.rawurlencode($groupeTag).'/'.$saisonId, ['playerTag' => $joueurTag]);
    }

    /** @return list<array<string, mixed>> */
    public function ligues(): array
    {
        return $this->get('leaguetiers')['items'] ?? [];
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $chemin, array $query = []): array
    {
        return $this->requete($chemin, $query)->toArray();
    }

    /**
     * @param array<string, string> $query
     */
    private function requete(string $chemin, array $query = []): ResponseInterface
    {
        $duree = $this->duree($chemin);
        if (!$this->cache || 0 === $duree) {
            return $this->client->request('GET', $this->baseUrl.$chemin, ['query' => $query]);
        }
        $cle = 'coc_'.sha1($chemin.'?'.http_build_query($query));
        $item = $this->cache->getItem($cle);
        if ($item->isHit()) {
            return new ReponseEnCache($item->get());
        }

        return new ReponseMemorisee($this->client->request('GET', $this->baseUrl.$chemin, ['query' => $query]), $this->cache, $cle, $duree);
    }

    /** Durée de conservation (secondes) selon ce qui change : les ligues presque jamais, un profil ou un groupe en cours souvent. */
    private function duree(string $chemin): int
    {
        return match (true) {
            'leaguetiers' === $chemin => 7 * 86400,
            str_ends_with($chemin, '/leaguehistory') => 6 * 3600,
            str_starts_with($chemin, 'leaguegroup/') => 900,
            default => 600,   // profil, combats
        };
    }
}
