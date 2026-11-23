<?php

namespace App\Tests\Service;

use App\Entity\Ligue;
use App\Service\ReglesLigue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReglesLigueTest extends KernelTestCase
{
    private ReglesLigue $regles;

    protected function setUp(): void
    {
        $this->regles = self::getContainer()->get(ReglesLigue::class);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function ligues(): iterable
    {
        // [identifiant Supercell, promus sur 100, relégués sur 100, nom]
        yield 'Dragon 2 : 20 % et 20 %' => [105000029, 20, 20, 'Dragon 2'];
        yield 'Dragon 3 : 25 % et 20 %' => [105000028, 25, 20, 'Dragon 3'];
        yield 'Archer 2 : 40 % et 10 %' => [105000008, 40, 10, 'Archer 2'];
        yield 'Légende III : top 5 fixe et 15 %' => [105000034, 5, 15, 'Légende III'];
        yield 'Légende II : top 3 fixe et 15 %' => [105000035, 3, 15, 'Légende II'];
    }

    #[DataProvider('ligues')]
    public function testLaRegleDependDeLaLigue(int $id, int $promus, int $relegues, string $nom): void
    {
        $regle = $this->regles->pour((new Ligue())->setIdSupercell($id)->setNom('x'), 100);

        self::assertNotNull($regle);
        self::assertSame($promus, $regle['promus']);
        self::assertSame($relegues, $regle['relegues']);
        self::assertSame($nom, $regle['nom']);
    }

    public function testUnPourcentageSAdapteALaTailleDuGroupe(): void
    {
        $regle = $this->regles->pour((new Ligue())->setIdSupercell(105000029)->setNom('x'), 50);

        self::assertSame(10, $regle['promus']);
    }

    public function testLeTopFixeDeLegendeNeDepasseJamaisLeGroupe(): void
    {
        $regle = $this->regles->pour((new Ligue())->setIdSupercell(105000034)->setNom('x'), 3);

        self::assertSame(3, $regle['promus']);
    }

    public function testUneLigueSansRegleConnueRenvoieNull(): void
    {
        // Skeleton 2, Légende I et « sans ligue » : on n'invente aucune règle.
        self::assertNull($this->regles->pour((new Ligue())->setIdSupercell(105000002)->setNom('x'), 100));
        self::assertNull($this->regles->pour((new Ligue())->setIdSupercell(105000036)->setNom('x'), 100));
        self::assertNull($this->regles->pour(null, 100));
    }
}
