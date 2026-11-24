<?php

namespace App\Service;

use App\Entity\Combat;
use App\Entity\Composition;
use App\Entity\GroupeLigue;
use App\Entity\Joueur;
use App\Entity\LigneComposition;
use App\Entity\Ligue;
use App\Entity\Participation;
use App\Entity\ResultatSemaine;
use App\Entity\Troupe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ETL : API Clash of Clans -> entités (règles T1 à T11 du mapping).
 * Relancer l'import ne duplique rien : chaque entité est retrouvée par sa clé naturelle.
 */
class ImportService
{
    /** Premier début de semaine connu : toutes les saisons en sont un multiple de 7 jours. */
    private const SAISON_REFERENCE = 1794805200;
    private const SEMAINE = 604800;

    /** @var array<string, Joueur> */
    private array $joueurs = [];
    /** @var array<int, Ligue> */
    private array $ligues = [];
    /** @var array<string, GroupeLigue> */
    private array $groupes = [];
    /** @var array<string, Composition> */
    private array $compositions = [];
    /** @var array<string, Troupe> */
    private array $troupes = [];
    /** @var array<string, Combat> */
    private array $combats = [];

    /** Nombre de joueurs du haut du classement analysés d'office ; les autres le sont sur demande. */
    public const PREMIERS = 20;

    private bool $liguesChargees = false;

    private int $ignores = 0;

    public function __construct(
        private CocApi $api,
        private ArmyDecoder $decodeur,
        private TroupeCatalogue $catalogue,
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    public function ignores(): int
    {
        return $this->ignores;
    }

    /**
     * Les ligues (/leaguetiers) sont chargées une seule fois.
     */
    public function chargerLigues(): void
    {
        if ($this->liguesChargees) {
            return;
        }
        $this->liguesChargees = true;
        foreach ($this->api->ligues() as $item) {
            $ligue = $this->em->getRepository(Ligue::class)->findOneBy(['idSupercell' => $item['id']]) ?? new Ligue();
            $ligue->setIdSupercell($item['id'])
                ->setNom($this->tronquer($item['name']))
                ->setUrlIcone($item['iconUrls']['small'] ?? null);
            if ($this->valider($ligue)) {
                $this->em->persist($ligue);
                $this->ligues[$item['id']] = $ligue;
            }
        }
        $this->em->flush();
    }

    /**
     * Importe un joueur : profil, groupe de la semaine (classement + attaques), combats, historique.
     *
     * @param array{profil?: ResponseInterface, historique?: ResponseInterface, battlelog: ResponseInterface}|null $reponses
     *                                                                                                                       requêtes déjà lancées en parallèle (voir CocApi::demanderJoueur) ; sinon elles sont faites ici, l'une après l'autre
     */
    public function importerJoueur(string $tag, bool $avecGroupe = true, ?array $reponses = null): Joueur
    {
        $this->chargerLigues();
        $tag = self::normaliserTag($tag);
        // On lit les 3 réponses AVANT de toucher à la base : si l'une échoue (API saturée…), rien n'est à moitié importé.
        // Membre d'un groupe : seuls les combats ont été demandés (voir CocApi::demanderJoueur), le reste est déjà connu.
        $complet = !$reponses || isset($reponses['profil']);
        $profil = $complet ? ($reponses ? $reponses['profil']->toArray() : $this->api->joueur($tag)) : [];
        $historique = $complet ? ($reponses ? ($reponses['historique']->toArray()['items'] ?? []) : $this->api->historique($tag)) : [];
        $combats = $reponses ? ($reponses['battlelog']->toArray()['items'] ?? []) : $this->api->battlelog($tag);

        $joueur = $this->joueur($tag, $profil['name'] ?? null);
        if ($complet) {
            $joueur->setNiveauHdv($profil['townHallLevel'] ?? null)
                ->setClanTag(isset($profil['clan']['tag']) ? self::normaliserTag($profil['clan']['tag']) : null)
                ->setClanNom($this->tronquer($profil['clan']['name'] ?? null))
                ->setLigue($this->ligues[$profil['leagueTier']['id'] ?? 0] ?? null);
        }

        // Ligue de chaque semaine passée, pour rattacher le groupe de la semaine précédente à la bonne ligue.
        $tiers = array_column($historique, 'leagueTierId', 'leagueSeasonId');

        if ($avecGroupe) {
            foreach ([['currentLeagueGroupTag', 'currentLeagueSeasonId'], ['previousLeagueGroupTag', 'previousLeagueSeasonId']] as [$cleTag, $cleSaison]) {
                if (isset($profil[$cleTag], $profil[$cleSaison])) {
                    $ligue = $this->ligues[$tiers[$profil[$cleSaison]] ?? 0] ?? $joueur->getLigue();
                    try {
                        $this->importerGroupe($joueur, $profil[$cleTag], (int) $profil[$cleSaison], $ligue);
                    } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
                        // Un groupe ancien peut ne plus être servi par l'API : on garde le reste.
                    }
                }
            }
        }
        $this->em->flush();

        $this->importerBattlelog($joueur, $combats);
        $this->importerHistorique($joueur, $historique);
        // Le joueur cherché a déjà ses combats : inutile de les redemander quand on analysera les membres de son groupe.
        if ($avecGroupe) {
            foreach ($this->em->getRepository(Participation::class)->findBy(['joueur' => $joueur]) as $participation) {
                $participation->setAttaquesImportees($participation->getAttaquesGagnees() + $participation->getAttaquesPerdues());
            }
        }
        // Marqué « analysé » seulement une fois tout importé.
        $joueur->setImporteLe(new \DateTimeImmutable());
        $this->em->flush();

        return $joueur;
    }

    /**
     * Groupes (semaine courante, précédente…) auxquels le joueur participe, du plus récent au plus ancien.
     *
     * @return list<GroupeLigue>
     */
    public function groupesDuJoueur(Joueur $joueur): array
    {
        return $this->em->createQuery('SELECT g FROM '.GroupeLigue::class.' g JOIN g.participations p WHERE p.joueur = :j ORDER BY g.saisonId DESC')
            ->setParameter('j', $joueur)->getResult();
    }

    /**
     * Importe les combats d'un morceau des membres d'un groupe (pour pouvoir suivre la progression à l'écran).
     * Un membre déjà importé récemment est sauté, ce qui permet de reprendre une analyse interrompue.
     *
     * @return array{int, int} [membres traités, total]
     */
    public function importerMembres(GroupeLigue $groupe, int $debut, int $nombre = 10, ?int $limite = null): array
    {
        // Du mieux classé au moins bien classé : avec une limite, seuls les premiers sont analysés (les autres sur demande).
        $participations = $this->classement($groupe);
        if (null !== $limite) {
            $participations = \array_slice($participations, 0, $limite);
        }

        // Seuls comptent les joueurs qui ont attaqué depuis leur dernier import : les autres n'ont rien de nouveau dans leurs combats.
        // Un joueur sans aucune attaque cette semaine n'a donc coûté aucun appel à l'API.
        $aImporter = [];
        foreach (\array_slice($participations, $debut, $nombre) as $participation) {
            $membre = $participation->getJoueur();
            if (!$participation->aDeNouvellesAttaques()) {
                continue;
            }
            if (0 === $participation->getAttaquesGagnees() + $participation->getAttaquesPerdues()) {
                $participation->setAttaquesImportees(0);
                $membre->setImporteLe($membre->getImporteLe() ?? new \DateTimeImmutable());
                continue;
            }
            $aImporter[$membre->getTag()] = $participation;
        }

        // Une requête par joueur (ses combats), toutes envoyées en même temps : c'est ce qui divise la durée de l'import.
        // La lecture des réponses (et l'écriture en base) se fait ensuite, joueur par joueur.
        $reponses = [];
        foreach ($aImporter as $tag => $participation) {
            $reponses[$tag] = $this->api->demanderJoueur($tag, complet: false);
        }

        foreach ($aImporter as $tag => $participation) {
            $membre = $participation->getJoueur();
            try {
                $this->importerJoueur($tag, avecGroupe: false, reponses: $reponses[$tag]);
                $participation->setAttaquesImportees($participation->getAttaquesGagnees() + $participation->getAttaquesPerdues());
            } catch (ExceptionInterface $e) {
                // Les réponses qu'on n'a pas lues sont abandonnées : sinon leur destruction relancerait l'erreur HTTP.
                foreach ($reponses[$tag] as $reponse) {
                    $reponse->cancel();
                }
                // API saturée ou réseau coupé : le joueur reste « à analyser » et sera repris au prochain passage.
                // Autre refus (profil privé ou introuvable) : inutile de réessayer, on note la tentative.
                if (!self::estTemporaire($e)) {
                    $participation->setAttaquesImportees($participation->getAttaquesGagnees() + $participation->getAttaquesPerdues());
                    $membre->setImporteLe(new \DateTimeImmutable());
                }
            }
        }
        $this->em->flush();

        return [min($debut + $nombre, \count($participations)), \count($participations)];
    }

    /**
     * Membres du groupe (les $limite premiers du classement, ou tous) dont les combats sont à importer
     * (jamais analysés, ou qui ont attaqué depuis, ou dont l'analyse a échoué temporairement).
     */
    public function restantsAAnalyser(GroupeLigue $groupe, ?int $limite = null): int
    {
        $participations = $this->classement($groupe);
        if (null !== $limite) {
            $participations = \array_slice($participations, 0, $limite);
        }

        return \count(array_filter($participations, static fn (Participation $p) => $p->aDeNouvellesAttaques()));
    }

    /**
     * Vrai tant que les premiers joueurs du classement (ceux qui font la méta) ne sont pas analysés :
     * c'est ce que l'écran attend automatiquement au premier affichage d'un groupe.
     */
    public function analyseIncomplete(GroupeLigue $groupe): bool
    {
        return $this->restantsAAnalyser($groupe, self::PREMIERS) > 0;
    }

    /**
     * Joueurs au-delà des premiers qui ont attaqué et dont les combats ne sont pas encore importés :
     * analysés seulement quand l'utilisateur le demande. Un joueur sans attaque ne coûte aucun appel, il n'est pas compté.
     */
    public function autresAAnalyser(GroupeLigue $groupe): int
    {
        return \count(array_filter(
            \array_slice($this->classement($groupe), self::PREMIERS),
            static fn (Participation $p) => $p->aDeNouvellesAttaques() && $p->getAttaquesGagnees() + $p->getAttaquesPerdues() > 0,
        ));
    }

    /**
     * @return list<Participation> les participations du groupe, du plus de trophées au moins
     */
    private function classement(GroupeLigue $groupe): array
    {
        return $this->em->createQuery(
            'SELECT p, j FROM '.Participation::class.' p JOIN p.joueur j WHERE p.groupe = :groupe ORDER BY p.trophees DESC, j.tag'
        )->setParameter('groupe', $groupe)->getResult();
    }

    /** Erreur qui peut disparaître en réessayant plus tard : API saturée (429), panne du serveur (5xx) ou réseau. */
    private static function estTemporaire(ExceptionInterface $e): bool
    {
        if ($e instanceof TransportExceptionInterface) {
            return true;
        }

        return $e instanceof HttpExceptionInterface && (429 === $e->getResponse()->getStatusCode() || $e->getResponse()->getStatusCode() >= 500);
    }

    private function importerGroupe(Joueur $joueur, string $groupeTag, int $saisonId, ?Ligue $ligue): void
    {
        $groupeTag = self::normaliserTag($groupeTag);
        $donnees = $this->api->groupe($groupeTag, $saisonId, $joueur->getTag());

        $groupe = $this->groupe($groupeTag, $saisonId, $ligue);
        // Un seul aller-retour pour les 100 membres au lieu d'une requête par membre.
        $this->prechargerJoueurs(array_map(static fn (array $m) => self::normaliserTag($m['playerTag']), $donnees['members'] ?? []));
        $existantes = [];
        if ($groupe->getId()) {
            foreach ($this->em->createQuery('SELECT p, j FROM '.Participation::class.' p JOIN p.joueur j WHERE p.groupe = :g')->setParameter('g', $groupe)->getResult() as $p) {
                $existantes[$p->getJoueur()->getId()] = $p;
            }
        }
        foreach ($donnees['members'] ?? [] as $membre) {
            $autre = $this->joueur(self::normaliserTag($membre['playerTag']), $membre['playerName'] ?? null);
            $autre->setClanTag(isset($membre['clanTag']) ? self::normaliserTag($membre['clanTag']) : null)
                ->setClanNom($this->tronquer($membre['clanName'] ?? null));
            $participation = ($autre->getId() ? ($existantes[$autre->getId()] ?? null) : null) ?? new Participation();
            $participation->setJoueur($autre)->setGroupe($groupe)
                ->setTrophees($membre['leagueTrophies'] ?? 0)
                ->setAttaquesGagnees($membre['attackWinCount'] ?? 0)
                ->setAttaquesPerdues($membre['attackLoseCount'] ?? 0)
                ->setDefensesGagnees($membre['defenseWinCount'] ?? 0)
                ->setDefensesPerdues($membre['defenseLoseCount'] ?? 0);
            if ($this->valider($participation)) {
                $this->em->persist($participation);
            }
        }
        $this->em->flush();

        // Attaques (T6) : le joueur importé attaque dans attackLogs et défend dans defenseLogs.
        foreach ($donnees['attackLogs'] ?? [] as $log) {
            $adversaire = $this->joueur(self::normaliserTag($log['opponentPlayerTag']), $log['opponentName'] ?? null);
            $this->combat($joueur, $adversaire, $log['stars'], $log['destructionPercentage'], $saisonId, $groupe, $log['trophies'] ?? null, $log['creationTime'] ?? null);
        }
        foreach ($donnees['defenseLogs'] ?? [] as $log) {
            $adversaire = $this->joueur(self::normaliserTag($log['opponentPlayerTag']), $log['opponentName'] ?? null);
            $this->combat($adversaire, $joueur, $log['stars'], $log['destructionPercentage'], $saisonId, $groupe, null, $log['creationTime'] ?? null);
        }
        $this->em->flush();
    }

    /**
     * @param list<array<string, mixed>> $combats
     */
    private function importerBattlelog(Joueur $joueur, array $combats): void
    {
        $groupesParSaison = [];
        foreach ($combats as $bataille) {
            // T2 : seulement les combats de ligue classée, village principal.
            if (!\in_array(strtolower($bataille['battleType'] ?? ''), ['ranked', 'legend'], true) || !isset($bataille['opponentPlayerTag'])) {
                continue;
            }
            $adversaire = $this->joueur(self::normaliserTag($bataille['opponentPlayerTag']), $bataille['opponentName'] ?? null);
            $adversaire->setNiveauHdv($bataille['opponentTownHallLevel'] ?? $adversaire->getNiveauHdv());
            $date = isset($bataille['battleTimestamp']) ? \DateTimeImmutable::createFromFormat('Ymd\THis.v\Z', $bataille['battleTimestamp'], new \DateTimeZone('UTC')) : false;
            if (!$date) {
                continue;
            }
            $saison = self::saisonDe($date);
            // Le groupe d'une semaine est le même pour tous les combats de cette semaine : une recherche par semaine, pas par combat.
            if (!\array_key_exists($saison, $groupesParSaison)) {
                $idGroupe = $this->groupeIdDuJoueur($joueur, $saison);
                $groupesParSaison[$saison] = $idGroupe ? $this->em->getRepository(GroupeLigue::class)->find($idGroupe) : null;
            }
            $groupe = $groupesParSaison[$saison];

            [$attaquant, $defenseur] = ($bataille['attack'] ?? false) ? [$joueur, $adversaire] : [$adversaire, $joueur];
            $combat = $this->combat($attaquant, $defenseur, $bataille['stars'], $bataille['destructionPercentage'], $saison, $groupe, null, $bataille['battleTimestamp'], strtoupper($bataille['battleType']) === 'LEGEND' ? 'LEGEND' : 'RANKED');
            if ($combat && !empty($bataille['armyShareCode'])) {
                $combat->setComposition($this->composition($bataille['armyShareCode']));
            }
        }
    }

    /** @param list<array<string, mixed>> $historique */
    private function importerHistorique(Joueur $joueur, array $historique): void
    {
        foreach ($historique as $item) {
            // T5 : une semaine passée déjà en base est ignorée.
            if ($this->em->getRepository(ResultatSemaine::class)->findOneBy(['joueur' => $joueur, 'saisonId' => $item['leagueSeasonId']])) {
                continue;
            }
            $resultat = (new ResultatSemaine())
                ->setJoueur($joueur)
                ->setLigue($this->ligues[$item['leagueTierId'] ?? 0] ?? null)
                ->setSaisonId($item['leagueSeasonId'])
                ->setClassement($item['placement'] ?? 0)
                ->setTrophees($item['leagueTrophies'] ?? 0)
                ->setAttaquesGagnees($item['attackWins'] ?? 0)
                ->setAttaquesPerdues($item['attackLosses'] ?? 0)
                ->setEtoilesAttaque($item['attackStars'] ?? 0)
                ->setDefensesGagnees($item['defenseWins'] ?? 0)
                ->setDefensesPerdues($item['defenseLosses'] ?? 0)
                ->setEtoilesDefense($item['defenseStars'] ?? 0)
                ->setCombatsMax($item['maxBattles'] ?? 0);
            if ($this->valider($resultat)) {
                $this->em->persist($resultat);
            }
        }
    }

    /**
     * Charge d'un coup les joueurs déjà connus parmi ces tags (évite une requête par joueur dans joueur()).
     *
     * @param list<string> $tags
     */
    private function prechargerJoueurs(array $tags): void
    {
        $manquants = array_values(array_diff(array_unique($tags), array_keys($this->joueurs)));
        foreach (array_chunk($manquants, 200) as $paquet) {
            foreach ($this->em->createQuery('SELECT j FROM '.Joueur::class.' j WHERE j.tag IN (:tags)')->setParameter('tags', $paquet)->getResult() as $joueur) {
                $this->joueurs[$joueur->getTag()] = $joueur;
            }
        }
    }

    private function joueur(string $tag, ?string $nom): Joueur
    {
        if (!isset($this->joueurs[$tag])) {
            $joueur = $this->em->getRepository(Joueur::class)->findOneBy(['tag' => $tag]) ?? (new Joueur())->setTag($tag);
            if ($this->valider($joueur->setNom($this->tronquer($nom) ?? $joueur->getNom()))) {
                $this->em->persist($joueur);
            }
            $this->joueurs[$tag] = $joueur;
        } elseif ($nom) {
            $this->joueurs[$tag]->setNom($this->tronquer($nom));
        }

        return $this->joueurs[$tag];
    }

    private function groupe(string $tag, int $saisonId, ?Ligue $ligue): GroupeLigue
    {
        $cle = $tag.'|'.$saisonId;
        if (!isset($this->groupes[$cle])) {
            $debut = new \DateTimeImmutable('@'.$saisonId);
            $groupe = $this->em->getRepository(GroupeLigue::class)->findOneBy(['tag' => $tag, 'saisonId' => $saisonId]) ?? new GroupeLigue();
            $groupe->setTag($tag)->setSaisonId($saisonId)->setDateDebut($debut)->setDateFin($debut->modify('+7 days'))->setLigue($ligue ?? $groupe->getLigue());
            $this->em->persist($groupe);
            $this->groupes[$cle] = $groupe;
        }

        return $this->groupes[$cle];
    }

    private function groupeIdDuJoueur(Joueur $joueur, int $saison): ?int
    {
        $id = $this->em->createQuery('SELECT g.id FROM '.Participation::class.' p JOIN p.groupe g WHERE p.joueur = :j AND g.saisonId = :s')
            ->setParameters(['j' => $joueur, 's' => $saison])->setMaxResults(1)->getOneOrNullResult();

        return $id['id'] ?? null;
    }

    private function combat(Joueur $attaquant, Joueur $defenseur, int $etoiles, int $pourcentage, int $saison, ?GroupeLigue $groupe, ?int $trophees, ?string $date, string $type = 'RANKED'): ?Combat
    {
        // T4 : l'API ne donne pas d'id de combat, et le même combat arrive par 2 endpoints.
        $empreinte = sha1(implode('|', [$attaquant->getTag(), $defenseur->getTag(), $etoiles, $pourcentage, $saison]));
        $combat = $this->combats[$empreinte] ?? $this->em->getRepository(Combat::class)->findOneBy(['empreinte' => $empreinte]);
        if (!$combat) {
            $combat = (new Combat())->setEmpreinte($empreinte)->setTypeCombat($type)
                ->setAttaquant($attaquant)->setDefenseur($defenseur)
                ->setEtoiles($etoiles)->setPourcentage($pourcentage);
            if (!$this->valider($combat)) {
                return null;
            }
            $this->em->persist($combat);
        }
        $this->combats[$empreinte] = $combat;
        $combat->setGroupe($groupe ?? $combat->getGroupe())
            ->setTrophees($trophees ?? $combat->getTrophees())
            ->setDateCombat($this->date($date) ?? $combat->getDateCombat());

        return $combat;
    }

    private function composition(string $code): Composition
    {
        if (isset($this->compositions[$code])) {
            return $this->compositions[$code];
        }
        $composition = $this->em->getRepository(Composition::class)->findOneBy(['code' => $code]);
        if (!$composition) {
            $armee = $this->decodeur->decoder($code);
            $composition = (new Composition())->setCode($code)
                ->setArchetypeTroupes($armee->archetypeTroupes())
                ->setArchetypeHeros($armee->archetypeHeros());
            foreach ($armee->heros as $nom) {
                $composition->addHero($this->troupe($nom));
            }
            $lignes = [];
            foreach ($armee->troupes as $t) {
                $lignes[$t['nom']] = ($lignes[$t['nom']] ?? 0) + $t['quantite'];
            }
            foreach ($armee->sorts as $s) {
                $lignes[$s['nom']] = ($lignes[$s['nom']] ?? 0) + $s['quantite'];
            }
            foreach ([...$armee->familiers, ...$armee->equipements] as $nom) {
                $lignes[$nom] = 1;
            }
            foreach ($lignes as $nom => $quantite) {
                $ligne = (new LigneComposition())->setTroupe($this->troupe($nom))->setQuantite($quantite);
                $composition->addLigne($ligne);
                $this->em->persist($ligne);
            }
            $this->em->persist($composition);
        }

        return $this->compositions[$code] = $composition;
    }

    private function troupe(string $nom): Troupe
    {
        if (!isset($this->troupes[$nom])) {
            $troupe = $this->em->getRepository(Troupe::class)->findOneBy(['nom' => $nom]);
            if (!$troupe) {
                $entree = $this->catalogue->parNom($nom);
                $troupe = (new Troupe())->setNom($nom)->setType($entree['type'] ?? 'troupe')->setIdJeu($entree['idJeu'] ?? null)->setPlace($entree['place'] ?? null);
                $this->em->persist($troupe);
            }
            $this->troupes[$nom] = $troupe;
        }

        return $this->troupes[$nom];
    }

    /** T1 : tag en majuscules, # ajouté s'il manque. */
    public static function normaliserTag(string $tag): string
    {
        return '#'.ltrim(strtoupper(trim($tag)), '#');
    }

    /** Début (id) de la semaine de ligue qui contient cette date. */
    public static function saisonDe(\DateTimeImmutable $date): int
    {
        return self::SAISON_REFERENCE + (int) floor(($date->getTimestamp() - self::SAISON_REFERENCE) / self::SEMAINE) * self::SEMAINE;
    }

    private function date(?string $valeur): ?\DateTimeImmutable
    {
        if (!$valeur) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Ymd\THis.v\Z', $valeur, new \DateTimeZone('UTC'));

        return $date ?: new \DateTimeImmutable($valeur);
    }

    /** T10 : noms coupés à 50 caractères. */
    private function tronquer(?string $texte): ?string
    {
        return null === $texte ? null : mb_substr($texte, 0, 50);
    }

    /** T11 : une entité invalide est ignorée, elle ne casse pas la base. */
    private function valider(object $entite): bool
    {
        if (\count($this->validator->validate($entite)) > 0) {
            ++$this->ignores;

            return false;
        }

        return true;
    }
}
