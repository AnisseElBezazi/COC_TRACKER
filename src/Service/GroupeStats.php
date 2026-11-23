<?php

namespace App\Service;

use App\Entity\Combat;
use App\Entity\GroupeLigue;
use App\Entity\Participation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Statistiques d'un groupe de ligue : méta, héros/équipements, top scorers, défense par archétype.
 *
 * Le périmètre est « les derniers combats des membres du groupe » : l'API ne garde que les ~50 dernières
 * batailles d'un joueur, qui débordent sur la semaine précédente et gardent donc le même échantillon utile
 * en début de semaine. Les attaques des membres donnent la méta, leurs défenses donnent l'analyse défensive.
 */
class GroupeStats
{
    /** @var array<int, list<array{joueur: int, hero: string, equipements: list<string>, etoiles: int, pourcentage: int}>> */
    private array $usagesEquipement = [];

    public function __construct(
        private EntityManagerInterface $em,
        private TroupeCatalogue $catalogue,
    ) {
    }

    /**
     * Méta : archétypes classés par taux de 3 étoiles (règle C3).
     * Avec $role = 'defenseur', ce sont les armées affrontées par les membres en défense.
     *
     * @return list<array{archetype: string, combats: int, tauxTroisEtoiles: float, etoilesMoyennes: float, pourcentageMoyen: float}>
     */
    public function meta(GroupeLigue $groupe, string $par = 'troupes', int $minimum = 10, string $role = 'attaquant'): array
    {
        $champ = 'heros' === $par ? 'co.archetypeHeros' : 'co.archetypeTroupes';

        $lignes = $this->em->createQueryBuilder()
            ->select($champ.' AS archetype, COUNT(c.id) AS combats, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS tauxTroisEtoiles, AVG(c.etoiles) AS etoilesMoyennes, AVG(c.pourcentage) AS pourcentageMoyen')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.'.$role.' IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->andWhere($champ.' IS NOT NULL')
            ->groupBy('archetype')
            ->having('COUNT(c.id) >= :min')
            ->orderBy('tauxTroisEtoiles', 'DESC')
            ->addOrderBy('etoilesMoyennes', 'DESC')
            ->setParameter('groupe', $groupe)
            ->setParameter('min', $minimum)
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $l) => [
            'archetype' => $l['archetype'],
            'combats' => (int) $l['combats'],
            'tauxTroisEtoiles' => round((float) $l['tauxTroisEtoiles'], 1),
            'etoilesMoyennes' => round((float) $l['etoilesMoyennes'], 2),
            'pourcentageMoyen' => round((float) $l['pourcentageMoyen'], 1),
        ], $lignes);
    }

    /**
     * Usage des héros, familiers ou équipements dans les attaques du groupe.
     *
     * @param 'heros'|'familier'|'equipement' $type
     *
     * @return list<array{nom: string, hero: ?string, combats: int, tauxTroisEtoiles: float}>
     */
    public function usage(GroupeLigue $groupe, string $type): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t.nom AS nom, COUNT(c.id) AS combats, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS tauxTroisEtoiles')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.attaquant IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->groupBy('t.nom')
            ->orderBy('combats', 'DESC')
            ->setParameter('groupe', $groupe);

        if ('heros' === $type) {
            $qb->join('co.heros', 't');
        } else {
            $qb->join('co.lignes', 'l')->join('l.troupe', 't')->andWhere('t.type = :type')->setParameter('type', $type);
        }

        return array_map(fn (array $l) => [
            'nom' => $l['nom'],
            'hero' => $this->catalogue->parNom($l['nom'])['hero'] ?? null,
            'combats' => (int) $l['combats'],
            'tauxTroisEtoiles' => round((float) $l['tauxTroisEtoiles'], 1),
        ], $qb->getQuery()->getArrayResult());
    }

    /**
     * Équipements les plus joués, rangés par héros.
     *
     * @return array<string, list<array{nom: string, hero: ?string, combats: int, tauxTroisEtoiles: float}>>
     */
    public function equipementsParHero(GroupeLigue $groupe): array
    {
        $parHero = [];
        foreach ($this->usage($groupe, 'equipement') as $ligne) {
            $parHero[$ligne['hero'] ?? 'Autres'][] = $ligne;
        }

        return $parHero;
    }

    /**
     * Top attaquants (attaques gagnées) et défenseurs (défenses gagnées), avec la moyenne d'étoiles observée.
     *
     * @return array{attaquants: list<array<string, mixed>>, defenseurs: list<array<string, mixed>>}
     */
    public function topScorers(GroupeLigue $groupe, int $nombre = 10): array
    {
        $participations = $this->em->getRepository(Participation::class)->findBy(['groupe' => $groupe]);

        $etoilesAttaque = $this->moyennes($groupe, 'attaquant');
        $etoilesDefense = $this->moyennes($groupe, 'defenseur');

        $ligne = static fn (Participation $p, array $moyennes, array $extra) => [
            'id' => $p->getJoueur()->getId(),
            'tag' => $p->getJoueur()->getTag(),
            'nom' => $p->getJoueur()->getNom(),
            'hdv' => $p->getJoueur()->getNiveauHdv(),
            'etoilesMoyennes' => isset($moyennes[$p->getJoueur()->getId()]) ? round($moyennes[$p->getJoueur()->getId()]['etoiles'], 2) : null,
            'pourcentageMoyen' => isset($moyennes[$p->getJoueur()->getId()]) ? round($moyennes[$p->getJoueur()->getId()]['pourcentage'], 1) : null,
            'echantillon' => $moyennes[$p->getJoueur()->getId()]['n'] ?? 0,
            ...$extra,
        ];

        $attaquants = $participations;
        usort($attaquants, static fn (Participation $a, Participation $b) => [$b->getAttaquesGagnees(), $b->getTrophees()] <=> [$a->getAttaquesGagnees(), $a->getTrophees()]);
        $defenseurs = $participations;
        usort($defenseurs, static fn (Participation $a, Participation $b) => [$b->getDefensesGagnees(), $a->getDefensesPerdues()] <=> [$a->getDefensesGagnees(), $b->getDefensesPerdues()]);

        return [
            'attaquants' => array_map(fn (Participation $p) => $ligne($p, $etoilesAttaque, ['gagnees' => $p->getAttaquesGagnees(), 'perdues' => $p->getAttaquesPerdues(), 'trophees' => $p->getTrophees()]), \array_slice($attaquants, 0, $nombre)),
            'defenseurs' => array_map(fn (Participation $p) => $ligne($p, $etoilesDefense, ['gagnees' => $p->getDefensesGagnees(), 'perdues' => $p->getDefensesPerdues(), 'trophees' => $p->getTrophees()]), \array_slice($defenseurs, 0, $nombre)),
        ];
    }

    /**
     * Pour chaque défenseur : l'archétype contre lequel il défend le mieux et celui qui le fait souffrir.
     *
     * @return array<int, array{meilleur: ?array<string, mixed>, pire: ?array<string, mixed>, archetypes: list<array<string, mixed>>}>
     */
    public function defenseParArchetype(GroupeLigue $groupe, ?int $joueurId = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d.id AS joueur, co.archetypeTroupes AS archetype, COUNT(c.id) AS n, AVG(c.etoiles) AS etoiles, AVG(c.pourcentage) AS pourcentage')
            ->from(Combat::class, 'c')
            ->join('c.defenseur', 'd')
            ->join('c.composition', 'co')
            ->where('c.defenseur IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->andWhere('co.archetypeTroupes IS NOT NULL')
            ->groupBy('d.id, co.archetypeTroupes')
            ->setParameter('groupe', $groupe);
        if (null !== $joueurId) {
            $qb->andWhere('d.id = :j')->setParameter('j', $joueurId);
        }

        $parJoueur = [];
        foreach ($qb->getQuery()->getArrayResult() as $l) {
            $parJoueur[$l['joueur']][] = [
                'archetype' => $l['archetype'],
                'n' => (int) $l['n'],
                'etoiles' => round((float) $l['etoiles'], 2),
                'pourcentage' => round((float) $l['pourcentage'], 1),
            ];
        }

        $resultat = [];
        foreach ($parJoueur as $id => $archetypes) {
            // On préfère les archétypes vus au moins 2 fois ; sinon on prend ce qu'on a.
            $fiables = array_values(array_filter($archetypes, static fn (array $a) => $a['n'] >= 2)) ?: $archetypes;
            usort($fiables, static fn (array $a, array $b) => [$a['etoiles'], $a['pourcentage'], -$a['n']] <=> [$b['etoiles'], $b['pourcentage'], -$b['n']]);
            usort($archetypes, static fn (array $a, array $b) => $b['n'] <=> $a['n']);
            $resultat[$id] = [
                'meilleur' => $fiables[0],
                'pire' => \count($fiables) > 1 ? end($fiables) : null,
                'archetypes' => $archetypes,
            ];
        }

        return $resultat;
    }

    /**
     * Les compositions (armées exactes, une par code d'armée) rattachées à un archétype, avec leurs résultats.
     * Même périmètre que la méta : les attaques des membres du groupe.
     *
     * @param 'troupes'|'heros'|'equipements' $famille
     * @param 'jouees'|'taux'                 $tri
     *
     * @return array{variantes: list<array{code: string, combats: int, taux: float, etoiles: float, destruction: float}>, total: int, distinctes: int}
     */
    public function compositionsDe(GroupeLigue $groupe, string $famille, string $nom, ?string $hero, string $tri = 'jouees', int $limite = 6): array
    {
        $creer = function (string $select) use ($groupe, $famille, $nom, $hero): ?\Doctrine\ORM\QueryBuilder {
            $qb = $this->em->createQueryBuilder()
                ->select($select)
                ->from(Combat::class, 'c')
                ->join('c.composition', 'co')
                ->where('c.attaquant IN (SELECT mj.id FROM App\\Entity\\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
                ->setParameter('groupe', $groupe);

            if ('troupes' === $famille) {
                $qb->andWhere('co.archetypeTroupes = :nom')->setParameter('nom', $nom);
            } elseif ('heros' === $famille) {
                $qb->andWhere('co.archetypeHeros = :nom')->setParameter('nom', $nom);
            } else {
                // Une paire d'équipements : chaque équipement appartient à un seul héros, donc leur présence dans l'armée suffit,
                // le héros n'est vérifié que pour écarter un nom d'équipement qui ne lui appartient pas.
                $equipements = array_values(array_filter(array_map('trim', explode('+', $nom))));
                if (!$equipements || \count($equipements) > 2 || null === $hero) {
                    return null;
                }
                $qb->join('co.heros', 'h')->andWhere('h.nom = :hero')->setParameter('hero', $hero);
                foreach ($equipements as $i => $equipement) {
                    $qb->join('co.lignes', 'l'.$i)->join('l'.$i.'.troupe', 't'.$i)
                        ->andWhere('t'.$i.'.nom = :e'.$i)->andWhere('t'.$i.'.type = :type')
                        ->setParameter('e'.$i, $equipement)->setParameter('type', 'equipement');
                }
            }

            return $qb;
        };

        $liste = $creer('co.code AS code, COUNT(DISTINCT c.id) AS combats, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS taux, AVG(c.etoiles) AS etoiles, AVG(c.pourcentage) AS pourcentage');
        $comptes = $creer('COUNT(DISTINCT c.id) AS total, COUNT(DISTINCT co.id) AS distinctes');
        if (null === $liste || null === $comptes) {
            return ['variantes' => [], 'total' => 0, 'distinctes' => 0];
        }

        $liste->groupBy('co.id');
        if ('taux' === $tri) {
            // Un taux sur 1 ou 2 attaques n'est pas une information : on exige au moins 3 attaques.
            $liste->having('COUNT(DISTINCT c.id) >= 3')->orderBy('taux', 'DESC')->addOrderBy('combats', 'DESC');
        } else {
            $liste->orderBy('combats', 'DESC')->addOrderBy('taux', 'DESC');
        }
        $liste->addOrderBy('co.code', 'ASC')->setMaxResults($limite);

        $totaux = $comptes->getQuery()->getSingleResult();

        return [
            'variantes' => array_map(static fn (array $l) => [
                'code' => $l['code'],
                'combats' => (int) $l['combats'],
                'taux' => round((float) $l['taux'], 1),
                'etoiles' => round((float) $l['etoiles'], 2),
                'destruction' => round((float) $l['pourcentage'], 1),
            ], $liste->getQuery()->getArrayResult()),
            'total' => (int) $totaux['total'],
            'distinctes' => (int) $totaux['distinctes'],
        ];
    }

    /**
     * L'armée exacte (code d'armée) la plus jouée par chaque membre, avec le nombre d'attaques concernées.
     *
     * @return array<int, array{code: string, n: int, total: int}> indexé par id de joueur
     */
    public function armeesFavorites(GroupeLigue $groupe): array
    {
        $lignes = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.attaquant) AS joueur, co.code AS code, COUNT(c.id) AS n')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.attaquant IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->groupBy('joueur, co.code')
            ->orderBy('n', 'DESC')
            ->addOrderBy('co.code', 'ASC')
            ->setParameter('groupe', $groupe)
            ->getQuery()->getArrayResult();

        $armees = [];
        $totaux = [];
        foreach ($lignes as $l) {
            $id = (int) $l['joueur'];
            $totaux[$id] = ($totaux[$id] ?? 0) + (int) $l['n'];
            $armees[$id] ??= ['code' => $l['code'], 'n' => (int) $l['n']];
        }
        foreach ($armees as $id => &$armee) {
            $armee['total'] = $totaux[$id];
        }

        return $armees;
    }

    /**
     * Archétype de troupes le plus utilisé à l'attaque par chaque membre du groupe (règle C4).
     *
     * @return array<int, array{archetype: string, n: int, total: int}> indexé par id de joueur
     */
    public function favoris(GroupeLigue $groupe): array
    {
        return $this->favorisPar($groupe, 'archetypeTroupes');
    }

    /**
     * @param 'archetypeTroupes'|'archetypeHeros' $champ
     *
     * @return array<int, array{archetype: string, n: int, total: int}>
     */
    private function favorisPar(GroupeLigue $groupe, string $champ): array
    {
        $lignes = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.attaquant) AS joueur, co.'.$champ.' AS archetype, COUNT(c.id) AS n')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.attaquant IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->andWhere('co.'.$champ.' IS NOT NULL')
            ->groupBy('joueur, co.'.$champ)
            ->orderBy('n', 'DESC')
            ->addOrderBy('co.'.$champ, 'ASC')
            ->setParameter('groupe', $groupe)
            ->getQuery()->getArrayResult();

        $favoris = [];
        $totaux = [];
        foreach ($lignes as $l) {
            $id = (int) $l['joueur'];
            $totaux[$id] = ($totaux[$id] ?? 0) + (int) $l['n'];
            // Les lignes arrivent triées par fréquence : la première de chaque joueur est son favori.
            $favoris[$id] ??= ['archetype' => $l['archetype'], 'n' => (int) $l['n']];
        }
        foreach ($favoris as $id => &$favori) {
            $favori['total'] = $totaux[$id];
        }

        return $favoris;
    }

    /**
     * Archétypes de héros joués par un joueur, du plus fréquent au moins fréquent.
     *
     * @return list<array{archetype: string, n: int, etoiles: float, tauxTroisEtoiles: float}>
     */
    public function herosDuJoueur(int $joueurId): array
    {
        $lignes = $this->em->createQueryBuilder()
            ->select('co.archetypeHeros AS archetype, COUNT(c.id) AS n, AVG(c.etoiles) AS etoiles, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS taux')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.attaquant = :j')
            ->andWhere('co.archetypeHeros IS NOT NULL')
            ->groupBy('co.archetypeHeros')
            ->orderBy('n', 'DESC')
            ->setParameter('j', $joueurId)
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $l) => [
            'archetype' => $l['archetype'],
            'n' => (int) $l['n'],
            'etoiles' => round((float) $l['etoiles'], 2),
            'tauxTroisEtoiles' => round((float) $l['taux'], 1),
        ], $lignes);
    }

    /**
     * Archétypes joués par un joueur à l'attaque, avec leurs résultats (règle C4 : le favori est le plus fréquent).
     *
     * @return list<array{archetype: string, heros: ?string, n: int, etoiles: float, tauxTroisEtoiles: float}>
     */
    public function attaquesDuJoueur(int $joueurId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('co.archetypeTroupes AS archetype, co.archetypeHeros AS heros, COUNT(c.id) AS n, AVG(c.etoiles) AS etoiles, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS taux')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->where('c.attaquant = :j')
            ->andWhere('co.archetypeTroupes IS NOT NULL')
            ->groupBy('co.archetypeTroupes, co.archetypeHeros')
            ->orderBy('n', 'DESC')
            ->setParameter('j', $joueurId);

        return array_map(static fn (array $l) => [
            'archetype' => $l['archetype'],
            'heros' => $l['heros'],
            'n' => (int) $l['n'],
            'etoiles' => round((float) $l['etoiles'], 2),
            'tauxTroisEtoiles' => round((float) $l['taux'], 1),
        ], $qb->getQuery()->getArrayResult());
    }

    /**
     * @param 'attaquant'|'defenseur' $role
     *
     * @return array<int, array{etoiles: float, pourcentage: float, n: int}>
     */
    private function moyennes(GroupeLigue $groupe, string $role): array
    {
        $lignes = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.'.$role.') AS joueur, AVG(c.etoiles) AS etoiles, AVG(c.pourcentage) AS pourcentage, COUNT(c.id) AS n')
            ->from(Combat::class, 'c')
            ->where('c.'.$role.' IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->groupBy('joueur')
            ->setParameter('groupe', $groupe)
            ->getQuery()->getArrayResult();

        $moyennes = [];
        foreach ($lignes as $l) {
            $moyennes[(int) $l['joueur']] = ['etoiles' => (float) $l['etoiles'], 'pourcentage' => (float) $l['pourcentage'], 'n' => (int) $l['n']];
        }

        return $moyennes;
    }

    /**
     * Archétypes d'équipement : un héros et la paire d'équipements qu'il porte, classés par taux de 3 étoiles
     * (même logique que la méta des troupes). Chaque équipement appartient à un seul héros, donc la paire
     * d'un héros se lit directement dans l'armée.
     *
     * @return list<array{hero: string, equipements: list<string>, combats: int, tauxTroisEtoiles: float, etoilesMoyennes: float, pourcentageMoyen: float}>
     */
    public function archetypesEquipement(GroupeLigue $groupe, int $minimum = 10): array
    {
        return $this->agreger($this->usages($groupe), $minimum);
    }

    /**
     * Les paires d'équipements jouées par un joueur, de la plus fréquente à la moins fréquente.
     *
     * @return list<array{hero: string, equipements: list<string>, combats: int, tauxTroisEtoiles: float, etoilesMoyennes: float, pourcentageMoyen: float}>
     */
    public function equipementsDuJoueur(GroupeLigue $groupe, int $joueurId): array
    {
        $lignes = $this->agreger(array_filter($this->usages($groupe), static fn (array $u) => $u['joueur'] === $joueurId), 1);
        usort($lignes, static fn (array $a, array $b) => $b['combats'] <=> $a['combats']);

        return $lignes;
    }

    /**
     * Une entrée par (attaque, héros) : l'équipement porté par chaque héros lors de chaque attaque des membres.
     *
     * @return list<array{joueur: int, hero: string, equipements: list<string>, etoiles: int, pourcentage: int}>
     */
    private function usages(GroupeLigue $groupe): array
    {
        $cle = (int) $groupe->getId();
        if (isset($this->usagesEquipement[$cle])) {
            return $this->usagesEquipement[$cle];
        }

        $lignes = $this->em->createQueryBuilder()
            ->select('c.id AS combat, IDENTITY(c.attaquant) AS joueur, c.etoiles AS etoiles, c.pourcentage AS pourcentage, t.nom AS nom')
            ->from(Combat::class, 'c')
            ->join('c.composition', 'co')
            ->join('co.lignes', 'l')
            ->join('l.troupe', 't')
            ->where('t.type = :type')
            ->andWhere('c.attaquant IN (SELECT mj.id FROM App\Entity\Participation mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)')
            ->setParameter('type', 'equipement')
            ->setParameter('groupe', $groupe)
            ->getQuery()->getArrayResult();

        $parCombat = [];
        foreach ($lignes as $l) {
            $hero = $this->catalogue->parNom($l['nom'])['hero'] ?? null;
            if (null === $hero) {
                continue;
            }
            $parCombat[$l['combat']]['meta'] = ['joueur' => (int) $l['joueur'], 'etoiles' => (int) $l['etoiles'], 'pourcentage' => (int) $l['pourcentage']];
            $parCombat[$l['combat']]['heros'][$hero][] = $l['nom'];
        }

        $usages = [];
        foreach ($parCombat as $combat) {
            foreach ($combat['heros'] as $hero => $noms) {
                sort($noms);
                $usages[] = ['hero' => $hero, 'equipements' => $noms, ...$combat['meta']];
            }
        }

        return $this->usagesEquipement[$cle] = $usages;
    }

    /**
     * @param iterable<array{joueur: int, hero: string, equipements: list<string>, etoiles: int, pourcentage: int}> $usages
     *
     * @return list<array{hero: string, equipements: list<string>, combats: int, tauxTroisEtoiles: float, etoilesMoyennes: float, pourcentageMoyen: float}>
     */
    private function agreger(iterable $usages, int $minimum): array
    {
        $groupes = [];
        foreach ($usages as $u) {
            $cle = $u['hero'].'|'.implode('+', $u['equipements']);
            $g = &$groupes[$cle];
            $g ??= ['hero' => $u['hero'], 'equipements' => $u['equipements'], 'combats' => 0, 'trois' => 0, 'etoiles' => 0, 'pourcentage' => 0];
            ++$g['combats'];
            $g['trois'] += 3 === $u['etoiles'] ? 1 : 0;
            $g['etoiles'] += $u['etoiles'];
            $g['pourcentage'] += $u['pourcentage'];
            unset($g);
        }

        $lignes = [];
        foreach ($groupes as $g) {
            if ($g['combats'] < $minimum) {
                continue;
            }
            $lignes[] = [
                'hero' => $g['hero'],
                'equipements' => $g['equipements'],
                'combats' => $g['combats'],
                'tauxTroisEtoiles' => round(100 * $g['trois'] / $g['combats'], 1),
                'etoilesMoyennes' => round($g['etoiles'] / $g['combats'], 2),
                'pourcentageMoyen' => round($g['pourcentage'] / $g['combats'], 1),
            ];
        }
        usort($lignes, static fn (array $a, array $b) => [$b['tauxTroisEtoiles'], $b['etoilesMoyennes'], $b['combats']] <=> [$a['tauxTroisEtoiles'], $a['etoilesMoyennes'], $a['combats']]);

        return $lignes;
    }
}
