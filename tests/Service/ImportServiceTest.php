<?php

namespace App\Tests\Service;

use App\Entity\Combat;
use App\Entity\Composition;
use App\Entity\GroupeLigue;
use App\Entity\Joueur;
use App\Entity\Participation;
use App\Entity\ResultatSemaine;
use App\Service\ArmyDecoder;
use App\Service\CocApi;
use App\Service\ImportService;
use App\Service\TroupeCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        $outil = new SchemaTool($this->em);
        $outil->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
        $outil->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testImporteUnJoueurSonGroupeSesCombatsEtSonHistorique(): void
    {
        $this->importeur()->importerJoueur('82u22qll');

        // T1 : le tag est normalisé.
        $alice = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#82U22QLL']);
        self::assertSame('Alice', $alice->getNom());
        self::assertSame(19, $alice->getNiveauHdv());
        self::assertSame('Dragon League 29', $alice->getLigue()->getNom());

        // Le groupe : 2 membres, donc 2 participations.
        self::assertCount(2, $this->em->getRepository(Participation::class)->findAll());

        // T4 : le même combat arrive par /leaguegroup et par /battlelog, il n'est créé qu'une fois,
        // avec les trophées du premier et l'armée du second.
        $combats = $this->em->getRepository(Combat::class)->findAll();
        self::assertCount(1, $combats);
        self::assertSame(40, $combats[0]->getTrophees());
        self::assertSame('Alice', $combats[0]->getAttaquant()->getNom());
        self::assertSame('Bob', $combats[0]->getDefenseur()->getNom());

        // T7/T8 : l'armée est décodée et rangée dans un archétype.
        $composition = $combats[0]->getComposition();
        self::assertSame('Dragon + Balloon', $composition->getArchetypeTroupes());
        self::assertSame('Barbarian King', $composition->getArchetypeHeros());

        self::assertCount(1, $this->em->getRepository(ResultatSemaine::class)->findAll());
    }

    public function testRelancerLImportNeDuppliqueRien(): void
    {
        $this->importeur()->importerJoueur('#82U22QLL');
        $compte = fn () => array_map(fn (string $c) => \count($this->em->getRepository($c)->findAll()), [Joueur::class, Participation::class, Combat::class, Composition::class, ResultatSemaine::class]);
        $avant = $compte();

        $this->em->clear();
        $this->importeur()->importerJoueur('#82U22QLL');

        self::assertSame($avant, $compte());
    }

    public function testImporteLesMembresDuGroupeParMorceauxEtMarqueLesJoueursAnalyses(): void
    {
        $importeur = $this->importeur();
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);

        // Seul le joueur importé est analysé : le groupe n'est pas complet, la barre d'analyse doit rester affichée.
        self::assertTrue($importeur->analyseIncomplete($groupe));

        [$fait, $total] = $importeur->importerMembres($groupe, 0, 10);

        self::assertSame([2, 2], [$fait, $total]);
        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        self::assertNotNull($bob->getImporteLe(), 'Bob est marqué comme analysé.');
        self::assertFalse($importeur->analyseIncomplete($groupe));
    }

    public function testUneApiSatureeLaisseLeJoueurAReessayerSansToutCasser(): void
    {
        // Les combats de Bob répondent 429 (trop de requêtes) : l'import du paquet continue, Bob reste « à analyser ».
        $importeur = $this->importeur(['players/%232PQ/battlelog' => 429]);
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);

        [$fait, $total] = $importeur->importerMembres($groupe, 0, 10);

        self::assertSame([2, 2], [$fait, $total]);
        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        self::assertNull($bob->getImporteLe(), 'Un refus temporaire ne marque pas le joueur comme analysé.');
        self::assertSame(1, $importeur->restantsAAnalyser($groupe));
        self::assertTrue($importeur->analyseIncomplete($groupe));
    }

    public function testSeulsLesMembresQuiOntAttaqueDepuisLeDernierImportCoutentUnAppel(): void
    {
        $importeur = $this->importeur();
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);

        // Alice (cherchée) a déjà ses combats ; Bob (3 attaques) a attaqué : ses combats sont à importer.
        self::assertSame(1, $importeur->restantsAAnalyser($groupe));
        $importeur->importerMembres($groupe, 0, 10);
        self::assertSame(0, $importeur->restantsAAnalyser($groupe));

        // Bob attaque encore : lui seul redevient « à analyser », sans toucher aux autres.
        $participation = $this->em->getRepository(Participation::class)->findOneBy(['groupe' => $groupe, 'joueur' => $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ'])]);
        $participation->setAttaquesGagnees($participation->getAttaquesGagnees() + 1);
        $this->em->flush();
        self::assertSame(1, $importeur->restantsAAnalyser($groupe));
    }

    public function testUnMembreSansAucuneAttaqueNeCoutePasDAppel(): void
    {
        $importeur = $this->importeur();
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);
        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        $participation = $this->em->getRepository(Participation::class)->findOneBy(['groupe' => $groupe, 'joueur' => $bob]);
        $participation->setAttaquesGagnees(0)->setAttaquesPerdues(0);
        $this->em->flush();

        // Le mock échoue sur tout appel inattendu : Bob n'a pas d'entrée battlelog dans ce scénario.
        $importeur = $this->importeur(['players/%232PQ/battlelog' => 500]);
        $importeur->importerMembres($groupe, 0, 10);

        self::assertSame(0, $importeur->restantsAAnalyser($groupe), 'Bob, sans attaque, est considéré à jour sans appel.');
    }

    public function testUnProfilIntrouvableEstNoteEtNeBloquePasLAnalyse(): void
    {
        // 404 : inutile de réessayer, on note la tentative pour que la barre d'analyse disparaisse.
        $importeur = $this->importeur(['players/%232PQ/battlelog' => 404]);
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);

        $importeur->importerMembres($groupe, 0, 10);

        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        self::assertNotNull($bob->getImporteLe());
        self::assertSame(0, $importeur->restantsAAnalyser($groupe));
    }

    public function testToutesLesRequetesDUnPaquetPartentAvantLaPremiereLecture(): void
    {
        // C'est ce qui rend l'import rapide : on compte les requêtes envoyées pendant le tout premier appel.
        $envoyees = [];
        $client = new MockHttpClient(static function (string $methode, string $url) use (&$envoyees) {
            $envoyees[] = $url;

            return new MockResponse('{"items": []}', ['response_headers' => ['content-type: application/json']]);
        });

        $reponses = (new CocApi('https://api.test/v1', $client))->demanderJoueur('#2PQ');

        self::assertCount(3, $envoyees, 'Profil, historique et combats sont demandés d\'un coup, sans rien lire.');
        self::assertSame(['profil', 'historique', 'battlelog'], array_keys($reponses));
    }

    public function testUnMembreDeGroupeNeCouteQuUnAppelALApi(): void
    {
        $envoyees = [];
        $client = new MockHttpClient(static function (string $methode, string $url) use (&$envoyees) {
            $envoyees[] = $url;

            return new MockResponse('{"items": []}', ['response_headers' => ['content-type: application/json']]);
        });

        $reponses = (new CocApi('https://api.test/v1', $client))->demanderJoueur('#2PQ', complet: false);

        self::assertSame(['battlelog'], array_keys($reponses));
        self::assertCount(1, $envoyees);
        self::assertStringEndsWith('/players/%232PQ/battlelog', $envoyees[0]);
    }

    public function testAnalyserLesMembresNeDemandeNiProfilNiHistorique(): void
    {
        $importeur = $this->importeur();
        $importeur->importerJoueur('#82U22QLL');
        $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);

        $importeur->importerMembres($groupe, 0, 10);

        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        self::assertNotNull($bob->getImporteLe());
        self::assertSame('Bob', $bob->getNom(), 'le nom vient du groupe, pas du profil');
    }

    /**
     * @param array<string, int> $statuts code HTTP à renvoyer pour un chemin donné (200 par défaut)
     */
    private function importeur(array $statuts = []): ImportService
    {
        $reponses = [
            'leaguetiers' => ['items' => [['id' => 105000029, 'name' => 'Dragon League 29', 'iconUrls' => ['small' => 'https://example.com/l.png']]]],
            'players/%2382U22QLL/battlelog' => ['items' => [
                ['battleType' => 'homeVillage', 'attack' => true, 'armyShareCode' => 'u1x0', 'opponentPlayerTag' => '#2PQ', 'stars' => 3, 'destructionPercentage' => 100, 'battleTimestamp' => '20261124T100000.000Z'],
                ['battleType' => 'ranked', 'attack' => true, 'armyShareCode' => 'h0u20x8-20x5', 'opponentPlayerTag' => '#2PQ', 'opponentName' => 'Bob', 'opponentTownHallLevel' => 18, 'stars' => 3, 'destructionPercentage' => 100, 'battleTimestamp' => '20261124T100000.000Z'],
            ]],
            'players/%2382U22QLL/leaguehistory' => ['items' => [['leagueSeasonId' => 1794805200, 'leagueTrophies' => 400, 'leagueTierId' => 105000029, 'placement' => 4, 'attackWins' => 8, 'attackLosses' => 0, 'attackStars' => 0, 'defenseWins' => 1, 'defenseLosses' => 2, 'defenseStars' => 5, 'maxBattles' => 14]]],
            'leaguegroup/%23998U29Y/1795410000' => [
                'members' => [
                    ['playerTag' => '#82U22QLL', 'playerName' => 'Alice', 'leagueTrophies' => 200, 'attackWinCount' => 5, 'attackLoseCount' => 0, 'defenseWinCount' => 0, 'defenseLoseCount' => 1],
                    ['playerTag' => '#2PQ', 'playerName' => 'Bob', 'leagueTrophies' => 90, 'attackWinCount' => 2, 'attackLoseCount' => 1, 'defenseWinCount' => 0, 'defenseLoseCount' => 1],
                ],
                'attackLogs' => [['opponentPlayerTag' => '#2PQ', 'opponentName' => 'Bob', 'stars' => 3, 'destructionPercentage' => 100, 'trophies' => 40, 'creationTime' => '2026-11-24T10:00:00Z']],
                'defenseLogs' => [],
            ],
            // Bob (membre du groupe) : seuls ses combats sont demandés (aucun profil, aucun historique : l'importeur échoue sur un appel inattendu).
            'players/%232PQ/battlelog' => ['items' => []],
            'players/%2382U22QLL' => ['tag' => '#82U22QLL', 'name' => 'Alice', 'townHallLevel' => 19, 'leagueTier' => ['id' => 105000029], 'clan' => ['tag' => '#2PPLQJ2UV', 'name' => 'Onni_cola'], 'currentLeagueGroupTag' => '#998U29Y', 'currentLeagueSeasonId' => 1795410000],
        ];

        $client = new MockHttpClient(function (string $methode, string $url) use ($reponses, $statuts) {
            $chemin = preg_replace('#^https?://[^/]+/v1/#', '', strtok($url, '?'));
            self::assertArrayHasKey($chemin, $reponses, 'Appel inattendu : '.$url);

            return new MockResponse(json_encode($reponses[$chemin]), [
                'http_code' => $statuts[$chemin] ?? 200,
                'response_headers' => ['content-type: application/json'],
            ]);
        });

        $conteneur = self::getContainer();

        return new ImportService(
            new CocApi('https://api.test/v1', $client),
            $conteneur->get(ArmyDecoder::class),
            $conteneur->get(TroupeCatalogue::class),
            $this->em,
            $conteneur->get(ValidatorInterface::class),
        );
    }
}
