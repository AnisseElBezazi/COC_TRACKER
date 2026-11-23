<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Catalogue statique du jeu (data/troupes.json) : id de jeu, type, place occupée,
 * et icône locale (public/img/troupes).
 */
class TroupeCatalogue
{
    /** @var list<array{nom: string, type: string, idJeu: int, place: ?int, hero?: string}> */
    private array $entrees;

    /** @var array<string, int> */
    private array $parNom = [];

    /** @var array<string, int> */
    private array $parId = [];

    /** @var array<string, true> */
    private array $icones = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/troupes.json')]
        string $fichier,
        #[Autowire('%kernel.project_dir%/public/img/troupes')]
        string $dossierIcones,
    ) {
        $this->entrees = json_decode((string) file_get_contents($fichier), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($this->entrees as $i => $entree) {
            $this->parNom[$entree['nom']] = $i;
            $this->parId[$entree['type'].':'.$entree['idJeu']] = $i;
        }
        foreach (glob($dossierIcones.'/*.webp') ?: [] as $chemin) {
            $this->icones[basename($chemin, '.webp')] = true;
        }
    }

    /**
     * @return array{nom: string, type: string, idJeu: int, place: ?int, hero?: string}|null
     */
    public function parId(string $type, int $idJeu): ?array
    {
        return isset($this->parId[$type.':'.$idJeu]) ? $this->entrees[$this->parId[$type.':'.$idJeu]] : null;
    }

    /**
     * @return array{nom: string, type: string, idJeu: int, place: ?int, hero?: string}|null
     */
    public function parNom(string $nom): ?array
    {
        return isset($this->parNom[$nom]) ? $this->entrees[$this->parNom[$nom]] : null;
    }

    /**
     * URL publique de l'icône d'une troupe, d'un héros, d'un sort ou d'un équipement ; null si inconnue.
     */
    public function image(string $nom): ?string
    {
        $slug = self::slug($nom);

        return isset($this->icones[$slug]) ? '/img/troupes/'.$slug.'.webp' : null;
    }

    public static function slug(string $nom): string
    {
        $nom = str_replace('p.e.k.k.a', 'pekka', strtolower($nom));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $nom), '_');
    }
}
