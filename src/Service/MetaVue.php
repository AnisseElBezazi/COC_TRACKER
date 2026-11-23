<?php

namespace App\Service;

/**
 * Prépare les données d'une méta (archétypes de troupes, de héros ou d'équipement) pour ses trois présentations :
 * une tier list S/A/B/C, une carte popularité × réussite, et le tableau classé.
 *
 * Les paliers sont relatifs au groupe (un classement, pas une note absolue) : S = les ~15 % meilleurs taux de 3 étoiles,
 * A jusqu'à 40 %, B jusqu'à 75 %, C le reste.
 */
class MetaVue
{
    /** Part du classement couverte par chaque palier, cumulée. */
    private const PALIERS = ['S' => 0.15, 'A' => 0.40, 'B' => 0.75, 'C' => 1.0];

    /** Pas « ronds » possibles pour l'axe de la popularité. */
    private const PAS_X = [1, 2, 2.5, 5, 10, 20, 25, 50];

    /**
     * @param list<array<string, mixed>>     $lignes lignes déjà classées par taux de 3 étoiles (voir GroupeStats::meta et archetypesEquipement)
     * @param 'troupes'|'heros'|'equipements' $type
     *
     * @return array{items: list<array<string, mixed>>, tiers: array<string, list<array<string, mixed>>>, axes: array<string, mixed>, total: int}
     */
    public function construire(array $lignes, string $type): array
    {
        $total = array_sum(array_column($lignes, 'combats'));
        $n = \count($lignes);

        $items = [];
        foreach (array_values($lignes) as $i => $l) {
            $estEquipement = 'equipements' === $type;
            $noms = $estEquipement ? $l['equipements'] : array_map('trim', explode('+', (string) $l['archetype']));
            $items[] = [
                'id' => $type.'-'.$i,
                'nom' => $estEquipement ? implode(' + ', $l['equipements']) : $l['archetype'],
                'hero' => $estEquipement ? $l['hero'] : null,
                'icones' => $estEquipement ? [$l['hero'], ...$l['equipements']] : $noms,
                'combats' => (int) $l['combats'],
                'part' => $total > 0 ? round(100 * $l['combats'] / $total, 1) : 0.0,
                'taux' => (float) $l['tauxTroisEtoiles'],
                'etoiles' => (float) $l['etoilesMoyennes'],
                'destruction' => (float) $l['pourcentageMoyen'],
                'rang' => $i + 1,
                'tier' => $this->palier($i, $n),
            ];
        }

        $axes = $this->axes($items);
        foreach ($items as &$item) {
            // Positions en pourcentage de la zone de tracé, bornées pour que l'icône ne déborde jamais.
            $item['x'] = max(3, min(97, $axes['xMax'] > 0 ? 100 * $item['part'] / $axes['xMax'] : 0));
            $item['y'] = max(4, min(96, 100 * ($item['taux'] - $axes['yMin']) / max(1, $axes['yMax'] - $axes['yMin'])));
        }
        unset($item);

        $tiers = array_fill_keys(array_keys(self::PALIERS), []);
        foreach ($items as $item) {
            $tiers[$item['tier']][] = $item;
        }

        return ['items' => $items, 'tiers' => $tiers, 'axes' => $axes, 'total' => $total];
    }

    private function palier(int $index, int $n): string
    {
        // Le meilleur est toujours en S, même sur un tout petit échantillon.
        $position = ($index + 1) / max(1, $n);
        foreach (self::PALIERS as $nom => $limite) {
            if ($position <= $limite + 1e-9 || 0 === $index) {
                return $nom;
            }
        }

        return 'C';
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function axes(array $items): array
    {
        if (!$items) {
            return ['xMax' => 0, 'yMin' => 0, 'yMax' => 100, 'xTicks' => [], 'yTicks' => [], 'medX' => 50, 'medY' => 50, 'medianePart' => 0.0, 'medianeTaux' => 0.0];
        }

        $parts = array_column($items, 'part');
        $taux = array_column($items, 'taux');

        $xMax = 5;
        foreach (self::PAS_X as $pas) {
            $xMax = $pas * ceil((max($parts) * 1.08) / $pas);
            if ($xMax / $pas <= 6) {
                break;
            }
        }
        $pasX = $xMax / max(1, round($xMax / ($xMax > 20 ? 10 : 5)));

        // L'axe vertical s'ajuste aux données (arrondi à la dizaine), sans jamais dépasser 0-100.
        $yMin = max(0, (int) (floor((min($taux) - 4) / 10) * 10));
        $yMax = min(100, (int) (ceil((max($taux) + 4) / 10) * 10));
        if ($yMax - $yMin < 20) {
            $yMax = min(100, $yMin + 20);
        }
        $pasY = ($yMax - $yMin) > 50 ? 20 : 10;

        $medX = $this->mediane($parts);
        $medY = $this->mediane($taux);

        return [
            'xMax' => $xMax,
            'yMin' => $yMin,
            'yMax' => $yMax,
            'xTicks' => $this->graduations(0, $xMax, $pasX, $xMax),
            'yTicks' => $this->graduations($yMin, $yMax, $pasY, $yMax - $yMin, $yMin),
            'medX' => min(100, 100 * $medX / max(0.0001, $xMax)),
            'medY' => min(100, max(0, 100 * ($medY - $yMin) / max(1, $yMax - $yMin))),
            'medianePart' => round($medX, 1),
            'medianeTaux' => round($medY, 1),
        ];
    }

    /**
     * @return list<array{valeur: float|int, pos: float}>
     */
    private function graduations(float|int $debut, float|int $fin, float|int $pas, float|int $etendue, float|int $origine = 0): array
    {
        $ticks = [];
        for ($v = $debut; $v <= $fin + 1e-9; $v += $pas) {
            $ticks[] = ['valeur' => $v, 'pos' => round(100 * ($v - $origine) / max(1, $etendue), 2)];
        }

        return $ticks;
    }

    /**
     * @param list<float> $valeurs
     */
    private function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = \count($valeurs);
        $milieu = intdiv($n, 2);

        return $n % 2 ? $valeurs[$milieu] : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }
}
