<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client de l'API Clash of Clans (proxy sans clé). Réessaie tout seul si l'API répond 429.
 */
class CocApi
{
    private HttpClientInterface $client;

    private string $baseUrl;

    public function __construct(
        #[Autowire(env: 'default::COC_API_URL')]
        ?string $baseUrl = null,
        ?HttpClientInterface $client = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?: 'https://api.quentindeglas.com/v1', '/').'/';
        $this->client = $client ?? new RetryableHttpClient(
            // Plusieurs requêtes en parallèle vers le même serveur : l'import d'un groupe en dépend.
            HttpClient::create(['timeout' => 30, 'max_host_connections' => 12]),
            new GenericRetryStrategy([429, 503], delayMs: 2000),
            maxRetries: 4,
        );
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
        return $this->client->request('GET', $this->baseUrl.$chemin, ['query' => $query]);
    }
}
