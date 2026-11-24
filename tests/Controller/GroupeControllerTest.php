<?php

namespace App\Tests\Controller;

use App\Entity\Combat;
use App\Entity\Composition;
use App\Entity\GroupeLigue;
use App\Entity\Joueur;
use App\Entity\Ligue;
use App\Entity\LigneComposition;
use App\Entity\Participation;
use App\Entity\Troupe;
use App\Service\ArmyDecoder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GroupeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        $outil = new SchemaTool($this->em);
        $outil->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
        $outil->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testAccueilAfficheLeFormulaireDeRecherche(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/rechercher"] input[name="tag"]');
    }

    public function testRechercheAvecUnTagInvalideRenvoieSurLAccueil(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->selectButton('Voir mes ligues')->form(['tag' => 'ZZZ']));

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.err', 'pas valide');
    }

    public function testLAccueilNeDemandeQueLeTag(): void
    {
        $this->creerGroupe();
        $crawler = $this->client->request('GET', '/');

        self::assertSelectorCount(1, 'form input[name="tag"]');
        self::assertCount(0, $crawler->filter('.gcard'), 'Plus de liste de groupes sur l\'accueil.');
        self::assertSelectorExists('.btn img[src="/img/trophies.png"]');
    }

    public function testLaSelectionDeLigueListeLesSemainesDuJoueur(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/ligues/2PP');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Alice');
        self::assertSelectorCount(1, 'a.lcard');
        self::assertSelectorTextContains('a.lcard', 'Dragon League 29');
        self::assertSelectorTextContains('a.lcard .ringin b', '#1');
        self::assertSelectorTextContains('a.lcard .ringin span', 'sur 2');
        self::assertSelectorExists(\sprintf('a.lcard[href="/groupe/%d"]', $groupe->getId()));
        self::assertSelectorExists('a.lcard img[src="https://example.com/ligue.png"]');
    }

    public function testUnJoueurInconnuRenvoie404SurLaSelection(): void
    {
        $this->client->request('GET', '/ligues/2PP');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLAnalyseDuGroupeExigeUnJeton(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('POST', '/groupe/'.$groupe->getId().'/analyser', ['debut' => 0]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnGroupeInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/groupe/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLaPageGroupeAfficheClassementMetaEtImagesDeTroupes(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertResponseIsSuccessful();
        // Classement prédit : les 2 joueurs, le mieux classé d'abord.
        self::assertSelectorCount(2, '#proj-pred .projrow');
        // Chaque ligne est sélectionnable : un clic ouvre la fiche du joueur dans le panneau de droite.
        self::assertSelectorCount(2, '#proj-pred .projrow.selectable[data-joueur]');
        self::assertSelectorExists('#split .listcol');
        self::assertSelectorExists('#split aside#panneau[aria-hidden="true"]');
        // Une fiche inerte (<template>) par joueur, remplie une seule fois, hors des listes.
        self::assertSelectorCount(2, '#fiches template[id^="fiche-"]');
        self::assertSelectorNotExists('#proj-pred .projrow .army');
        $alice = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PP']);
        $fiche = '#fiche-'.$alice->getId();
        self::assertSelectorTextContains($fiche.' .pn-id h3', 'Alice');
        self::assertSelectorExists($fiche.' [data-fermer]');
        self::assertSelectorExists($fiche.' .hdv-big[data-i="th18"]');
        // La fiche montre l'armée comme dans le jeu : cartes de héros (avec familier et équipements), troupes avec quantités, sorts, château.
        self::assertSelectorTextContains($fiche.' .army-note', '×12/12');
        self::assertSelectorCount(1, $fiche.' .army .hcard');
        self::assertSelectorTextContains($fiche.' .army .hcard', 'Barbarian King');
        self::assertSelectorCount(2, $fiche.' .army .hcard .eq');
        self::assertSelectorCount(1, $fiche.' .army .hcard .pet');
        self::assertSelectorExists($fiche.' .army .unit img[data-i="dragon"]');
        self::assertSelectorTextContains($fiche.' .army .unit .qty', '×20');
        self::assertSelectorTextContains($fiche.' .army', 'Château de clan');
        self::assertSelectorExists($fiche.' a.btn-profil');
        self::assertSelectorExists('#proj-pred .legend');
        // Le détail se lit comme un calcul : déjà gagnés + à gagner = prévus, puis l'avancement et le niveau en étoiles.
        self::assertSelectorTextContains($fiche.' .calcrow', 'gagnés');
        self::assertSelectorTextContains($fiche.' .calcrow', 'à venir');
        self::assertSelectorTextContains($fiche.' .calcrow', 'prévus');
        self::assertSelectorTextContains($fiche.' .progrow', '12/14');
        // Peu de texte : les explications sont dans des infobulles (attribut title), pas affichées.
        self::assertSelectorExists($fiche.' .calcrow[title*="par combat"]');
        self::assertSelectorExists($fiche.' .perf .stars');
        // Les trophées sont toujours reconnaissables : l'icône du trophée est dans la pastille dorée.
        self::assertSelectorExists('#proj-pred .legend + .projrow .pr-tot img[src="/img/trophies.png"]');
        // L'explication du calcul n'est plus un bloc de texte : elle est dans l'infobulle de l'en-tête.
        self::assertSelectorNotExists('details.calc');
        self::assertSelectorExists('#sec-projection .dph[title*="combats restants"]');
        self::assertSelectorTextContains('#proj-pred .legend + .projrow', 'Alice');
        // L'archétype le plus utilisé de chaque joueur est affiché dans les listes (Bob n'a jamais attaqué).
        self::assertSelectorTextContains('#proj-pred .legend + .projrow .fav', 'Dragon + Balloon');
        // Bob n'a jamais été analysé : on le dit, au lieu d'afficher un « inconnu » ambigu.
        self::assertSelectorTextContains('#proj-pred .legend + .projrow + .projrow .fav', 'Analyse en cours');
        // Les mêmes joueurs apparaissent dans la vue « Maintenant ».
        self::assertSelectorCount(2, '#proj-actuel .projrow');
        // La vue « Maintenant » n'affiche plus de « prévu #n » sous les trophées.
        self::assertStringNotContainsString('prévu #', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('&lt;span', $this->client->getResponse()->getContent());

        // La méta montre les images des troupes, pas seulement leurs noms.
        self::assertSelectorExists('#sec-meta img[data-i="dragon"]');
        self::assertSelectorExists('#sec-meta img[data-i="balloon"]');
        self::assertSelectorTextContains('#sec-meta', 'Dragon + Balloon');
        // Navigation : 3 onglets (sous-vues et filtres sont montés par le script).
        self::assertSelectorCount(3, '#dtabs [role="tab"]');
        self::assertSelectorNotExists('#tab-classement');
        self::assertSelectorNotExists('#sec-classement');
        self::assertSelectorExists('#dsub');
        self::assertSelectorCount(6, '.dsection[role="tabpanel"]');
        // Une barre de recherche remplace les filtres Top 10 / Top 25 / Tous.
        self::assertSelectorExists('#recherche[type="search"]');
        self::assertSelectorNotExists('[data-n]');
    }

    public function testLesZonesDePromotionEtDeRelegationDependentDeLaLigue(): void
    {
        $groupe = $this->creerGroupeDeDix(105000029); // Dragon 2 : 20 % promus, 20 % relégués -> 2 et 2 sur 10 joueurs

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.dkpis', 'Top 2');
        self::assertSelectorExists('.dkpi[title*="Dragon 2"]'); // le nom de la ligue est dans l'infobulle
        self::assertSelectorTextContains('#proj-pred .zondiv:not(.rel)', 'TOP 2');
        self::assertSelectorTextContains('#proj-pred .zondiv.rel', '−2');
        // Rangs 1 et 2 : promotion ; rangs 9 et 10 : relégation ; les autres sans zone.
        self::assertCount(2, $crawler->filter('#proj-pred .projrow.promo'));
        self::assertCount(2, $crawler->filter('#proj-pred .projrow.demo'));
        self::assertStringNotContainsString('TOP 5', $this->client->getResponse()->getContent());
    }

    public function testEnLegendeIIILePodiumDePromotionEstLeTopCinq(): void
    {
        $groupe = $this->creerGroupeDeDix(105000034); // Légende III : top 5 promus, 15 % relégués -> 2 sur 10 (arrondi)

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertSelectorTextContains('.dkpis', 'Top 5');
        self::assertSelectorExists('.dkpi[title*="Légende III"]');
        self::assertCount(5, $crawler->filter('#proj-pred .projrow.promo'));
    }

    public function testUneLigueSansReglePasLesZones(): void
    {
        $groupe = $this->creerGroupeDeDix(105000002); // Skeleton 2 : règle non renseignée, on n'invente rien

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertSelectorExists('.dkpi[title*="non renseignée"]');
        self::assertSelectorTextContains('.dkpis', 'Promotion');
        self::assertCount(0, $crawler->filter('#proj-pred .projrow.promo'));
        self::assertCount(0, $crawler->filter('#proj-pred .zondiv'));
    }

    /**
     * Un groupe de 10 joueurs aux trophées décroissants, dans la ligue d'identifiant donné.
     */
    private function creerGroupeDeDix(int $idLigue, int $nombre = 10): GroupeLigue
    {
        $ligue = (new Ligue())->setIdSupercell($idLigue)->setNom('Ligue '.$idLigue);
        $groupe = (new GroupeLigue())->setTag('#GRP')->setSaisonId(1795410000)
            ->setDateDebut(new \DateTimeImmutable('@1795410000'))->setDateFin(new \DateTimeImmutable('@1796014800'))->setLigue($ligue);
        $this->em->persist($ligue);
        $this->em->persist($groupe);
        for ($i = 1; $i <= $nombre; ++$i) {
            $joueur = (new Joueur())->setTag('#2P'.$i)->setNom('Joueur '.$i)->setNiveauHdv(17);
            $this->em->persist($joueur);
            $this->em->persist((new Participation())->setJoueur($joueur)->setGroupe($groupe)->setTrophees(1000 - 50 * $i)
                ->setAttaquesGagnees(10)->setAttaquesPerdues(0)->setDefensesGagnees(0)->setDefensesPerdues(0));
        }
        $this->em->flush();
        $this->em->clear();

        return $this->em->find(GroupeLigue::class, $groupe->getId());
    }

    public function testLesHerosSontAffichesParDeux(): void
    {
        $groupe = $this->creerGroupe();
        $this->em->clear();
        // Une armée à 4 héros : ils doivent apparaître par paires (2 + 2).
        $quatre = (new Composition())->setCode('quatre-heros')
            ->setArchetypeTroupes('Dragon + Balloon')
            ->setArchetypeHeros('Archer Queen + Barbarian King + Grand Warden + Royal Champion');
        $this->em->persist($quatre);
        $alice = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PP']);
        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        $groupe = $this->em->find(GroupeLigue::class, $groupe->getId());
        for ($i = 0; $i < 12; ++$i) {
            $this->em->persist((new Combat())->setEmpreinte(sha1('quatre'.$i))->setTypeCombat('RANKED')
                ->setAttaquant($alice)->setDefenseur($bob)->setGroupe($groupe)->setComposition($quatre)->setEtoiles(3)->setPourcentage(100));
        }
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        $ligne = $crawler->filter('#meta-heros .projrow')->reduce(static fn ($n) => str_contains($n->text(), 'Royal Champion'));
        self::assertCount(1, $ligne);
        $duos = $ligne->filter('.duos .duo');
        self::assertCount(2, $duos, 'Quatre héros = deux paires.');
        self::assertStringContainsString('Archer Queen + Barbarian King', $duos->eq(0)->text());
        self::assertStringContainsString('Grand Warden + Royal Champion', $duos->eq(1)->text());
    }

    public function testUnJoueurAnalyseSansAttaqueRankedLeDitClairement(): void
    {
        $groupe = $this->creerGroupe();
        $bob = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PQ']);
        $bob->setImporteLe(new \DateTimeImmutable());
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertSelectorTextContains('#proj-pred .legend + .projrow + .projrow .fav', 'Pas d\'attaque ranked récente');
        self::assertStringNotContainsString('Analyse en cours', $crawler->filter('#proj-pred .legend + .projrow + .projrow .pr-fav')->text());
    }

    public function testLaBarreDAnalyseResteAfficheeTantQuUnMembreNEstPasAnalyse(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId());
        self::assertSelectorExists('#analyse');

        // Une fois tous les membres analysés, la barre disparaît.
        foreach ($this->em->getRepository(Participation::class)->findAll() as $participation) {
            $participation->setAttaquesImportees($participation->getAttaquesGagnees() + $participation->getAttaquesPerdues());
        }
        $this->em->flush();
        $this->em->clear();
        $this->client->request('GET', '/groupe/'.$groupe->getId());
        self::assertSelectorNotExists('#analyse');
    }

    public function testLAnalyseAutomatiqueSeLimiteAuxVingtPremiersEtLeResteSeFaitAuClic(): void
    {
        $groupe = $this->creerGroupeDeDix(105000029, 30);

        // Rien d'analysé : la barre se lance toute seule, sur les 20 premiers seulement.
        $this->client->request('GET', '/groupe/'.$groupe->getId());
        self::assertSelectorExists('#analyse[data-auto="1"]');
        self::assertSelectorTextContains('#analyse-compte', '0 / 20');
        self::assertSelectorNotExists('#analyse-go');

        // Les 20 premiers sont faits : plus d'analyse automatique, un bouton propose les 10 autres.
        foreach ($this->em->getRepository(Participation::class)->findBy(['groupe' => $groupe], ['trophees' => 'DESC'], 20) as $participation) {
            $participation->setAttaquesImportees($participation->getAttaquesGagnees() + $participation->getAttaquesPerdues());
        }
        $this->em->flush();
        $this->em->clear();
        $this->client->request('GET', '/groupe/'.$groupe->getId());
        self::assertSelectorExists('#analyse[data-auto="0"]');
        self::assertSelectorTextContains('#analyse-go', 'Analyser les 10 autres');
    }

    public function testLaMetaListeLesArchetypesDEquipementParHeros(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertResponseIsSuccessful();
        // Les équipements sont une famille d'archétypes de la Méta, filtrable par héros.
        self::assertSelectorExists('#sec-meta [data-mds="equipements"]');
        self::assertSelectorExists('#mchips [data-mhero="Barbarian King"]');
        self::assertSelectorExists('#sec-meta .mdata[data-ds="equipements"] .mtile[data-hero="Barbarian King"]');
        self::assertSelectorNotExists('#sec-equip');
        // Le tableau reste le jumeau complet de la tier list et de la carte.
        self::assertSelectorTextContains('#equip-tous .projrow:first-child', 'Giant Gauntlet + Spiky Ball');
        self::assertSelectorTextContains('#equip-tous .projrow:first-child', 'Barbarian King');
        self::assertSelectorExists('#equip-tous img[data-i="giant_gauntlet"]');
        self::assertSelectorExists('#equip-tous img[data-i="barbarian_king"]');
    }

    public function testLaMetaSePresenteEnTierListCarteEtTableau(): void
    {
        $groupe = $this->creerGroupe();

        $crawler = $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertResponseIsSuccessful();
        // Trois présentations, un seul sélecteur au-dessus de ce qu'il pilote.
        self::assertSelectorCount(3, '#sec-meta [data-mvue]');
        self::assertSelectorCount(3, '#sec-meta [data-mds]');
        $troupes = '#sec-meta .mdata[data-ds="troupes"]';

        // Tier list : l'unique archétype est le meilleur, donc en S, avec ses icônes et son taux.
        self::assertSelectorExists($troupes.' .mview[data-vue="tiers"] .tier-s .tier-badge');
        self::assertSelectorTextContains($troupes.' .tier-s .tier-badge', 'S');
        self::assertSelectorExists($troupes.' .tier-s .mtile img[data-i="dragon"]');
        self::assertSelectorExists($troupes.' .tier-s .mtile[data-nom="Dragon + Balloon"][data-combats="12"]');
        self::assertSelectorTextContains($troupes.' .tier-s .mtile .mt-val', '50');   // 6 attaques à 3★ sur 12 = 50 %

        // Carte : un marqueur par archétype, avec son quadrant et ses axes.
        self::assertSelectorCount(1, $troupes.' .mview[data-vue="carte"] .mpoint');
        self::assertSelectorExists($troupes.' .mpoint.tier-s[data-nom="Dragon + Balloon"]');
        self::assertSelectorTextContains($troupes.' .mmap', 'Pépites');
        self::assertSelectorTextContains($troupes.' .mmap', 'Valeurs sûres');
        self::assertSelectorTextContains($troupes.' .m-xtitle', 'Popularité');
        self::assertGreaterThan(0, $crawler->filter($troupes.' .m-yaxis span')->count());

        // Tableau : le jumeau accessible de la tier list et de la carte.
        self::assertSelectorTextContains($troupes.' .mview[data-vue="tableau"] #meta-troupes', 'Dragon + Balloon');

        // Le détail épinglé est présent (rempli par le script) et la bulle ne remplace pas les valeurs : elles sont dans l'aria-label.
        self::assertSelectorExists('#mdetail');
        self::assertSelectorExists($troupes.' .mtile[aria-label*="de 3 étoiles sur 12 attaques"]');
    }

    public function testLesCompositionsDUnArchetypeSontListeesAvecLeurArmee(): void
    {
        $groupe = $this->creerGroupe();
        $page = '/groupe/'.$groupe->getId();

        // La page prévoit l'emplacement et l'URL du fragment.
        $this->client->request('GET', $page);
        self::assertSelectorExists('#mcompos[data-url="'.$page.'/compositions"]');

        $this->client->request('GET', $page.'/compositions', ['famille' => 'troupes', 'nom' => 'Dragon + Balloon']);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.cv-card');
        self::assertSelectorTextContains('.cv-head', '1 variante');
        self::assertSelectorTextContains('.cv-head', '12 attaques');
        self::assertSelectorTextContains('.cv-card .cv-rate', '50');
        self::assertSelectorExists('.cv-card .army .hcard');
        self::assertSelectorExists('[data-ctri="taux"]');
    }

    public function testLesCompositionsRefusentUneFamilleInconnue(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId().'/compositions', ['famille' => 'nimporte', 'nom' => 'x']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testLesCompositionsSansResultatAffichentUneNote(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId().'/compositions', ['famille' => 'troupes', 'nom' => 'Inconnu', 'tri' => 'taux']);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.cv-card');
        self::assertSelectorExists('.cv-note');
    }

    public function testLeBoutonProfilCompletEstDansLaFiche(): void
    {
        $groupe = $this->creerGroupe();

        $this->client->request('GET', '/groupe/'.$groupe->getId());

        self::assertSelectorExists('template[id^="fiche-"]');
        self::assertStringContainsString('Voir le profil complet', $this->client->getResponse()->getContent());
    }

    public function testLaPageJoueurMontreSonArchetypeFavori(): void
    {
        $groupe = $this->creerGroupe();
        $alice = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => '#2PP']);

        $this->client->request('GET', \sprintf('/groupe/%d/joueur/%d', $groupe->getId(), $alice->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.dtop .t1', 'Alice');
        // L'hôtel de ville s'affiche seul, en grand, sans pastille de texte.
        self::assertSelectorExists('.dtop img.hdv-big[data-i="th18"]');
        self::assertSelectorNotExists('.dtop .badge-date');
        self::assertSelectorTextContains('.dkpis', 'Dragon + Balloon');
        self::assertSelectorExists('.projrow img[data-i="dragon"]');
        // Archétypes de héros et d'équipement du joueur.
        self::assertSelectorTextContains('body', 'Son armée la plus jouée');
        self::assertSelectorExists('.armywrap .army .hcard');
        self::assertSelectorTextContains('body', 'Ses archétypes de héros');
        self::assertSelectorTextContains('body', 'Giant Gauntlet + Spiky Ball');
    }

    /**
     * Un groupe de 2 joueurs ; Alice fait 12 attaques avec Dragon + Balloon, Bob défend.
     */
    private function creerGroupe(): GroupeLigue
    {
        $decodeur = self::getContainer()->get(ArmyDecoder::class);
        // 20 Dragon (id 8) + 20 Balloon (id 5), héros Barbarian King.
        $armee = $decodeur->decoder('h0u20x8-20x5');

        $ligue = (new Ligue())->setIdSupercell(105000029)->setNom('Dragon League 29')->setUrlIcone('https://example.com/ligue.png');
        $groupe = (new GroupeLigue())->setTag('#GRP')->setSaisonId(1795410000)
            ->setDateDebut(new \DateTimeImmutable('@1795410000'))->setDateFin(new \DateTimeImmutable('@1796014800'))->setLigue($ligue);
        $composition = (new Composition())->setCode('h0p16e8_14u20x8-20x5s2x2i1x3')
            ->setArchetypeTroupes($armee->archetypeTroupes())->setArchetypeHeros($armee->archetypeHeros());
        $alice = (new Joueur())->setTag('#2PP')->setNom('Alice')->setNiveauHdv(18);
        $bob = (new Joueur())->setTag('#2PQ')->setNom('Bob')->setNiveauHdv(17);

        // Barbarian King porte Giant Gauntlet + Spiky Ball.
        $equipements = [];
        foreach (['Giant Gauntlet', 'Spiky Ball'] as $nom) {
            $troupe = (new Troupe())->setNom($nom)->setType('equipement');
            $equipements[] = $troupe;
            $this->em->persist($troupe);
            $ligne = (new LigneComposition())->setTroupe($troupe)->setQuantite(1);
            $composition->addLigne($ligne);
            $this->em->persist($ligne);
        }

        foreach ([$ligue, $groupe, $composition, $alice, $bob] as $entite) {
            $this->em->persist($entite);
        }
        foreach ([[$alice, 300, 10, 2], [$bob, 100, 3, 1]] as [$joueur, $trophees, $gagnees, $perdues]) {
            $this->em->persist((new Participation())->setJoueur($joueur)->setGroupe($groupe)->setTrophees($trophees)
                ->setAttaquesGagnees($gagnees)->setAttaquesPerdues($perdues)->setDefensesGagnees(0)->setDefensesPerdues(0));
        }
        for ($i = 0; $i < 12; ++$i) {
            $this->em->persist((new Combat())->setEmpreinte(sha1('combat'.$i))->setTypeCombat('RANKED')
                ->setAttaquant($alice)->setDefenseur($bob)->setGroupe($groupe)->setComposition($composition)
                ->setEtoiles($i % 2 ? 3 : 2)->setPourcentage(90));
        }
        $this->em->flush();
        $this->em->clear();

        return $this->em->find(GroupeLigue::class, $groupe->getId());
    }
}
