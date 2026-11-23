<?php

namespace App\Tests\Service;

use App\Service\ArmyDecoder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ArmyDecoderTest extends KernelTestCase
{
    private ArmyDecoder $decodeur;

    protected function setUp(): void
    {
        $this->decodeur = self::getContainer()->get(ArmyDecoder::class);
    }

    public function testDecodeHerosTroupesEtSorts(): void
    {
        // Barbarian King (0) + Archer Queen (1) ; 10 Barbarian (0) et 2 Giant (3) ; 3 Rage (2) et 1 Poison (9).
        $armee = $this->decodeur->decoder('h0-1u10x0-2x3s3x2-1x9');

        self::assertSame(['Barbarian King', 'Archer Queen'], $armee->heros);
        self::assertSame(['Barbarian' => 10, 'Giant' => 2], array_column($armee->troupes, 'quantite', 'nom'));
        self::assertSame(['Rage' => 3, 'Poison' => 1], array_column($armee->sorts, 'quantite', 'nom'));
    }

    public function testArchetypeTroupesPrendLesDeuxTroupesQuiOccupentLePlusDePlace(): void
    {
        // 10 Barbarian = 10 places, 2 Giant = 10 places, 1 Wizard = 4 places : Wizard est écarté.
        $armee = $this->decodeur->decoder('u10x0-2x3-1x6');

        self::assertCount(2, explode(' + ', (string) $armee->archetypeTroupes()));
        self::assertStringNotContainsString('Wizard', (string) $armee->archetypeTroupes());
    }

    public function testArchetypeHerosEstEnOrdreAlphabetique(): void
    {
        $armee = $this->decodeur->decoder('h1-0');

        self::assertSame('Archer Queen + Barbarian King', $armee->archetypeHeros());
    }

    public function testLeChateauDeClanEstIgnore(): void
    {
        // i = troupes du château, d = sorts du château : ils ne comptent pas dans l'archétype.
        $armee = $this->decodeur->decoder('i1x3d1x9u5x0');

        self::assertSame(['Barbarian'], array_column($armee->troupes, 'nom'));
        self::assertSame([], $armee->sorts);
    }

    public function testFamiliersEtEquipementsDesHeros(): void
    {
        $armee = $this->decodeur->decoder('h0p16e8_14');

        self::assertSame(['Barbarian King'], $armee->heros);
        self::assertCount(1, $armee->familiers);
        self::assertCount(2, $armee->equipements);
    }

    public function testUnCodeVideOuInconnuNeCassePas(): void
    {
        $armee = $this->decodeur->decoder('');

        self::assertSame([], $armee->troupes);
        self::assertNull($armee->archetypeTroupes());
        self::assertNull($armee->archetypeHeros());
    }

    public function testChaqueHerosGardeSonFamilierEtSesEquipements(): void
    {
        // Barbarian King (0) avec le familier 16 et les équipements 8 et 14 ; Archer Queen (1) avec le familier 3 et les équipements 17 et 39.
        $armee = $this->decodeur->decoder('h0p16e8_14-1p3e17_39u10x0');

        self::assertCount(2, $armee->herosDetail);
        self::assertSame('Barbarian King', $armee->herosDetail[0]['nom']);
        self::assertNotNull($armee->herosDetail[0]['familier']);
        self::assertCount(2, $armee->herosDetail[0]['equipements']);
        self::assertSame('Archer Queen', $armee->herosDetail[1]['nom']);
        self::assertNotSame($armee->herosDetail[0]['familier'], $armee->herosDetail[1]['familier']);
        self::assertCount(2, $armee->herosDetail[1]['equipements']);
    }

    public function testLesSiegesChateauEtPlacesSontSepares(): void
    {
        // 5 Barbarian (5 places), 1 engin de siège (id 87), 2 sorts Rage (id 2), château : 1 Giant (id 3) et 1 sort Poison (id 9).
        $armee = $this->decodeur->decoder('u5x0-1x87s2x2i1x3d1x9');

        self::assertSame(['Barbarian'], array_column($armee->troupesSansSieges(), 'nom'));
        self::assertCount(1, $armee->sieges());
        self::assertSame(5, $armee->placesTroupes());
        self::assertSame('Giant', $armee->chateauTroupes[0]['nom']);
        self::assertSame('Poison', $armee->chateauSorts[0]['nom']);
        self::assertGreaterThan(0, $armee->placesSorts());
    }
}
