<?php

namespace App\Tests\Service;

use App\Service\MetaVue;
use PHPUnit\Framework\TestCase;

class MetaVueTest extends TestCase
{
    private MetaVue $vue;

    protected function setUp(): void
    {
        $this->vue = new MetaVue();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lignes(int $n): array
    {
        $lignes = [];
        for ($i = 0; $i < $n; ++$i) {
            // Classées par taux décroissant, comme GroupeStats::meta.
            $lignes[] = ['archetype' => 'Dragon + Troupe'.$i, 'combats' => 10 + 2 * $i, 'tauxTroisEtoiles' => 80.0 - 5 * $i, 'etoilesMoyennes' => 2.6 - 0.05 * $i, 'pourcentageMoyen' => 95.0 - $i];
        }

        return $lignes;
    }

    public function testLesPaliersSontRelatifsAuClassement(): void
    {
        $vue = $this->vue->construire(self::lignes(20), 'troupes');

        // 20 archétypes : 15 % = 3 en S, jusqu'à 40 % = 8 en tout pour S+A, jusqu'à 75 % = 15 pour S+A+B.
        self::assertCount(3, $vue['tiers']['S']);
        self::assertCount(5, $vue['tiers']['A']);
        self::assertCount(7, $vue['tiers']['B']);
        self::assertCount(5, $vue['tiers']['C']);
        self::assertSame('Dragon + Troupe0', $vue['tiers']['S'][0]['nom']);
    }

    public function testLeMeilleurEstToujoursEnSMemeAvecPeuDArchetypes(): void
    {
        self::assertSame('S', $this->vue->construire(self::lignes(1), 'troupes')['items'][0]['tier']);
        self::assertSame('S', $this->vue->construire(self::lignes(2), 'troupes')['items'][0]['tier']);
    }

    public function testLesPositionsRestentDansLaZoneDeTrace(): void
    {
        $vue = $this->vue->construire(self::lignes(12), 'troupes');

        foreach ($vue['items'] as $item) {
            self::assertGreaterThanOrEqual(3, $item['x']);
            self::assertLessThanOrEqual(97, $item['x']);
            self::assertGreaterThanOrEqual(4, $item['y']);
            self::assertLessThanOrEqual(96, $item['y']);
        }
        // Plus un archétype réussit, plus il est haut.
        self::assertGreaterThan($vue['items'][11]['y'], $vue['items'][0]['y']);
    }

    public function testLaPartCumuleCentPourCent(): void
    {
        $vue = $this->vue->construire(self::lignes(8), 'troupes');

        self::assertEqualsWithDelta(100.0, array_sum(array_column($vue['items'], 'part')), 0.6);
    }

    public function testLesAxesSAjustentAuxDonneesSansDepasserZeroCent(): void
    {
        $vue = $this->vue->construire(self::lignes(10), 'troupes');

        self::assertGreaterThanOrEqual(0, $vue['axes']['yMin']);
        self::assertLessThanOrEqual(100, $vue['axes']['yMax']);
        self::assertGreaterThan(0, $vue['axes']['xMax']);
        self::assertNotEmpty($vue['axes']['xTicks']);
        self::assertNotEmpty($vue['axes']['yTicks']);
        // Les médianes (lignes de repère des quadrants) tombent dans la zone.
        self::assertGreaterThanOrEqual(0, $vue['axes']['medX']);
        self::assertLessThanOrEqual(100, $vue['axes']['medX']);
    }

    public function testUnArchetypeDEquipementPorteSonHeroEtSesIcones(): void
    {
        $vue = $this->vue->construire([
            ['hero' => 'Barbarian King', 'equipements' => ['Giant Gauntlet', 'Spiky Ball'], 'combats' => 12, 'tauxTroisEtoiles' => 66.7, 'etoilesMoyennes' => 2.5, 'pourcentageMoyen' => 93.0],
        ], 'equipements');

        self::assertSame('Giant Gauntlet + Spiky Ball', $vue['items'][0]['nom']);
        self::assertSame('Barbarian King', $vue['items'][0]['hero']);
        self::assertSame(['Barbarian King', 'Giant Gauntlet', 'Spiky Ball'], $vue['items'][0]['icones']);
    }

    public function testUneListeVideNeCassePas(): void
    {
        $vue = $this->vue->construire([], 'troupes');

        self::assertSame([], $vue['items']);
        self::assertSame(0, $vue['total']);
    }
}
