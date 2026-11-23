<?php

namespace App\Service;

use App\Dto\ArmeeDecodee;

/**
 * Découpe un armyShareCode du jeu, ex. « h0p16e8_14-1p3e17_39u44x12-1x87s11x10 » :
 *  - h : héros (id, p = familier, e = équipements séparés par _), séparés par -
 *  - u : troupes et engins, « quantité x id », séparés par -
 *  - s : sorts, même format
 *  - i / d : troupes et sorts du château de clan (affichés, mais ils ne définissent pas l'archétype).
 */
class ArmyDecoder
{
    private const SEPARATEUR = '/h(?<h>[^idus]+)|i(?<i>[\d+x-]+)|d(?<d>[\d+x-]+)|u(?<u>[\d+x-]+)|s(?<s>[\d+x-]+)/';

    public function __construct(private TroupeCatalogue $catalogue)
    {
    }

    public function decoder(string $code): ArmeeDecodee
    {
        $troupes = $sorts = $heros = $familiers = $equipements = $herosDetail = $chateauTroupes = $chateauSorts = [];

        preg_match_all(self::SEPARATEUR, $code, $segments, \PREG_SET_ORDER);
        foreach ($segments as $segment) {
            if (($segment['h'] ?? '') !== '') {
                foreach (array_filter(explode('-', $segment['h']), strlen(...)) as $morceau) {
                    if (!preg_match('/^(\d+)(?:m\d+)?(?:p(\d+))?(?:e(\d+)(?:_(\d+))?)?$/', $morceau, $m)) {
                        continue;
                    }
                    $hero = $this->catalogue->parId('heros', (int) $m[1]);
                    if (!$hero) {
                        continue;
                    }
                    $heros[] = $hero['nom'];
                    $detail = ['nom' => $hero['nom'], 'familier' => null, 'equipements' => []];
                    if (isset($m[2]) && '' !== $m[2] && ($familier = $this->catalogue->parId('familier', (int) $m[2]))) {
                        $familiers[] = $familier['nom'];
                        $detail['familier'] = $familier['nom'];
                    }
                    foreach ([3, 4] as $i) {
                        if (isset($m[$i]) && '' !== $m[$i] && ($equipement = $this->catalogue->parId('equipement', (int) $m[$i]))) {
                            $equipements[] = $equipement['nom'];
                            $detail['equipements'][] = $equipement['nom'];
                        }
                    }
                    $herosDetail[] = $detail;
                }
            } elseif (($segment['u'] ?? '') !== '') {
                foreach ($this->unites($segment['u']) as [$quantite, $id]) {
                    if ($troupe = $this->catalogue->parId('troupe', $id)) {
                        $troupes[] = ['nom' => $troupe['nom'], 'quantite' => $quantite, 'place' => $troupe['place'] ?? 1, 'siege' => 'sieges' === ($troupe['source'] ?? '')];
                    }
                }
            } elseif (($segment['s'] ?? '') !== '') {
                foreach ($this->unites($segment['s']) as [$quantite, $id]) {
                    if ($sort = $this->catalogue->parId('sort', $id)) {
                        $sorts[] = ['nom' => $sort['nom'], 'quantite' => $quantite, 'place' => $sort['place'] ?? 1];
                    }
                }
            } elseif (($segment['i'] ?? '') !== '') {
                foreach ($this->unites($segment['i']) as [$quantite, $id]) {
                    if ($troupe = $this->catalogue->parId('troupe', $id)) {
                        $chateauTroupes[] = ['nom' => $troupe['nom'], 'quantite' => $quantite];
                    }
                }
            } elseif (($segment['d'] ?? '') !== '') {
                foreach ($this->unites($segment['d']) as [$quantite, $id]) {
                    if ($sort = $this->catalogue->parId('sort', $id)) {
                        $chateauSorts[] = ['nom' => $sort['nom'], 'quantite' => $quantite];
                    }
                }
            }
        }

        return new ArmeeDecodee($troupes, $sorts, $heros, $familiers, $equipements, $herosDetail, $chateauTroupes, $chateauSorts);
    }

    /**
     * @return list<array{int, int}> paires [quantité, id]
     */
    private function unites(string $segment): array
    {
        $unites = [];
        foreach (array_filter(explode('-', $segment), strlen(...)) as $morceau) {
            if (preg_match('/^(\d+)x(\d+)$/', $morceau, $m)) {
                $unites[] = [(int) $m[1], (int) $m[2]];
            }
        }

        return $unites;
    }
}
