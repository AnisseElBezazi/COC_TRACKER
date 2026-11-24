<?php

namespace App\Tests\Service;

use App\Entity\Combat;
use App\Entity\GroupeLigue;
use App\Entity\Joueur;
use App\Entity\Participation;
use App\Service\ArmyDecoder;
use App\Service\CocApi;
use App\Service\ImportService;
use App\Service\TroupeCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * Mode hors ligne : si le serveur de l'API est en panne, l'application lit les réponses enregistrées dans fixtures/api.
 */
class HorsLigneTest extends KernelTestCase
{
    private const TAG = '#82U22QLL';

    private function api(): CocApi
    {
        return new CocApi('fixtures/api', projet: self::getContainer()->getParameter('kernel.project_dir'));
    }

    public function testLesReponsesViennentDesFichiersJson(): void
    {
        $profil = $this->api()->joueur(self::TAG);

        self::assertSame(self::TAG, $profil['tag']);
        self::assertArrayHasKey('currentLeagueGroupTag', $profil);
        self::assertNotEmpty($this->api()->ligues());
        self::assertNotEmpty($this->api()->battlelog(self::TAG));
    }

    public function testUnFichierAbsentRepondComme404(): void
    {
        $this->expectException(ClientExceptionInterface::class);

        $this->api()->joueur('#PPPPPPP');
    }

    public function testOnImporteUnJoueurSonGroupeEtSesMembresSansReseau(): void
    {
        $conteneur = self::getContainer();
        $em = $conteneur->get(EntityManagerInterface::class);
        $em->getConfiguration()->setUseSchemaEditorApi(true);
        $outil = new SchemaTool($em);
        $outil->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $outil->createSchema($em->getMetadataFactory()->getAllMetadata());
        $importeur = new ImportService($this->api(), $conteneur->get(ArmyDecoder::class), $conteneur->get(TroupeCatalogue::class), $em, $conteneur->get(ValidatorInterface::class));

        $joueur = $importeur->importerJoueur(self::TAG);

        self::assertNotNull($joueur->getNom());
        $groupe = $em->getRepository(GroupeLigue::class)->findOneBy(['tag' => '#998U29Y']);
        self::assertNotNull($groupe, 'le groupe de la semaine est importé depuis fixtures/api');
        self::assertGreaterThan(50, \count($em->getRepository(Participation::class)->findBy(['groupe' => $groupe])));

        // Les 20 premiers du classement ont un fichier de combats : ils sont analysés, les autres répondent 404 (ignorés).
        foreach ([0, 10, 20] as $debut) {
            $importeur->importerMembres($groupe, $debut, 10, limite: ImportService::PREMIERS);
        }
        self::assertSame(0, $importeur->restantsAAnalyser($groupe, ImportService::PREMIERS));
        self::assertNotEmpty($em->getRepository(Combat::class)->findAll());
        self::assertGreaterThan(1, \count($em->getRepository(Joueur::class)->findAll()));
    }
}
