<?php

namespace App\Service;

use App\Entity\Ligue;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Règles de promotion et de relégation d'un groupe de ligue, qui dépendent du palier :
 * un pourcentage du groupe (ex. 20 % en Dragon 2) ou un nombre fixe de places (ex. top 5 en Légende III).
 *
 * Les valeurs viennent de data/ligues.json. Supercell les ajuste souvent : un palier absent du fichier
 * n'a pas de règle connue, on ne l'invente pas.
 */
class ReglesLigue
{
    /** @var array<string, array{nom: string, promotion?: array{type: string, valeur: int}, relegation?: array{type: string, valeur: int}, source: string}> */
    private array $regles;

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/ligues.json')]
        string $fichier,
    ) {
        $this->regles = json_decode((string) file_get_contents($fichier), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{nom: string, promus: int, relegues: int, source: string}|null null si la règle de cette ligue est inconnue
     */
    public function pour(?Ligue $ligue, int $tailleGroupe): ?array
    {
        $regle = $this->regles[(string) $ligue?->getIdSupercell()] ?? null;
        if (null === $regle || $tailleGroupe < 1) {
            return null;
        }

        $promus = $this->places($regle['promotion'] ?? null, $tailleGroupe);
        $relegues = $this->places($regle['relegation'] ?? null, $tailleGroupe);
        if (0 === $promus && 0 === $relegues) {
            return null;
        }

        return ['nom' => $regle['nom'], 'promus' => $promus, 'relegues' => $relegues, 'source' => $regle['source']];
    }

    /**
     * @param array{type: string, valeur: int}|null $zone
     */
    private function places(?array $zone, int $tailleGroupe): int
    {
        if (null === $zone) {
            return 0;
        }
        $places = 'rang' === $zone['type'] ? $zone['valeur'] : (int) round($tailleGroupe * $zone['valeur'] / 100);

        return max(0, min($tailleGroupe, $places));
    }
}
