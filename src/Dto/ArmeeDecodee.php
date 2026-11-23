<?php

namespace App\Dto;

/**
 * Contenu d'un armyShareCode décodé. Les listes contiennent des noms du catalogue.
 */
final readonly class ArmeeDecodee
{
    /**
     * @param list<array{nom: string, quantite: int, place: int, siege?: bool}> $troupes     troupes et engins de siège
     * @param list<array{nom: string, quantite: int, place?: int}>              $sorts
     * @param list<string>                                                      $heros       noms des héros
     * @param list<string>                                                      $familiers
     * @param list<string>                                                      $equipements
     * @param list<array{nom: string, familier: ?string, equipements: list<string>}> $herosDetail chaque héros avec son familier et ses équipements
     * @param list<array{nom: string, quantite: int}>                           $chateauTroupes
     * @param list<array{nom: string, quantite: int}>                           $chateauSorts
     */
    public function __construct(
        public array $troupes = [],
        public array $sorts = [],
        public array $heros = [],
        public array $familiers = [],
        public array $equipements = [],
        public array $herosDetail = [],
        public array $chateauTroupes = [],
        public array $chateauSorts = [],
    ) {
    }

    /**
     * Les 2 troupes qui prennent le plus de place (règle T8), ex. « Dragon + Balloon ».
     */
    public function archetypeTroupes(): ?string
    {
        $troupes = $this->troupes;
        usort($troupes, static fn (array $a, array $b) => ($b['quantite'] * $b['place']) <=> ($a['quantite'] * $a['place']));
        $noms = array_map(static fn (array $t) => $t['nom'], \array_slice($troupes, 0, 2));

        return $noms ? implode(' + ', $noms) : null;
    }

    /**
     * Les héros par ordre alphabétique (règle T8), ex. « Archer Queen + Barbarian King ».
     */
    public function archetypeHeros(): ?string
    {
        $heros = $this->heros;
        sort($heros);

        return $heros ? implode(' + ', $heros) : null;
    }

    /**
     * Les troupes de l'armée, sans les engins de siège (affichés à part).
     *
     * @return list<array{nom: string, quantite: int, place: int, siege?: bool}>
     */
    public function troupesSansSieges(): array
    {
        return array_values(array_filter($this->troupes, static fn (array $t) => !($t['siege'] ?? false)));
    }

    /**
     * @return list<array{nom: string, quantite: int, place: int, siege?: bool}>
     */
    public function sieges(): array
    {
        return array_values(array_filter($this->troupes, static fn (array $t) => $t['siege'] ?? false));
    }

    /** Places occupées par les troupes (hors sièges), comme le compteur « 352/352 » du jeu. */
    public function placesTroupes(): int
    {
        return array_sum(array_map(static fn (array $t) => $t['quantite'] * $t['place'], $this->troupesSansSieges()));
    }

    /** Places occupées par les sorts. */
    public function placesSorts(): int
    {
        return array_sum(array_map(static fn (array $s) => $s['quantite'] * ($s['place'] ?? 1), $this->sorts));
    }
}
