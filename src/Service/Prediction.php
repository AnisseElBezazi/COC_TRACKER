<?php

namespace App\Service;

use App\Entity\GroupeLigue;
use App\Entity\Participation;
use App\Entity\ResultatSemaine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prédiction du classement de fin de semaine (règle C1) :
 * trophées actuels + trophées moyens par combat × combats restants.
 *
 * La moyenne par combat de chaque joueur est lissée : tant qu'il a joué peu de combats cette semaine, on la
 * tire vers son rythme habituel (ses semaines passées, ResultatSemaine), lui-même tiré vers celui du groupe
 * s'il a peu d'historique. Sans cela, un joueur à 0 combat serait projeté au niveau du meilleur.
 */
class Prediction
{
    /** Poids du groupe dans la moyenne, exprimé en nombre de combats « virtuels ». */
    private const LISSAGE = 4;
    /** Poids du groupe dans le rythme historique d'un joueur, en combats « virtuels ». */
    private const LISSAGE_HISTORIQUE = 10;
    private const COMBATS_PAR_DEFAUT = 14;

    public function __construct(
        private EntityManagerInterface $em,
        private GroupeStats $stats,
    ) {
    }

    /**
     * @return list<array<string, mixed>> triée par classement prédit
     */
    public function predire(GroupeLigue $groupe): array
    {
        $participations = $this->em->getRepository(Participation::class)->findBy(['groupe' => $groupe]);
        if (!$participations) {
            return [];
        }

        $faits = static fn (Participation $p) => $p->getAttaquesGagnees() + $p->getAttaquesPerdues();
        $totalFaits = array_sum(array_map($faits, $participations));
        $moyenneGroupe = $totalFaits > 0 ? array_sum(array_map(static fn (Participation $p) => $p->getTrophees(), $participations)) / $totalFaits : 0.0;
        $maxDuGroupe = max(array_map($faits, $participations));

        [$historique, $historiqueGroupe] = $this->rythmesHistoriques($groupe);

        $scorers = $this->stats->topScorers($groupe, \count($participations));
        $attaque = array_column($scorers['attaquants'], null, 'id');
        $defense = array_column($scorers['defenseurs'], null, 'id');

        $actuel = $participations;
        usort($actuel, static fn (Participation $a, Participation $b) => $b->getTrophees() <=> $a->getTrophees());
        $rangActuel = [];
        foreach ($actuel as $i => $p) {
            $rangActuel[$p->getId()] = $i + 1;
        }

        $lignes = [];
        foreach ($participations as $p) {
            $joueur = $p->getJoueur();
            $joues = $faits($p);
            $maximum = $this->combatsMax($joueur->getId()) ?? max($maxDuGroupe, self::COMBATS_PAR_DEFAUT);
            $restants = max(0, $maximum - $joues);
            $parCombat = $joues > 0 ? $p->getTrophees() / $joues : 0.0;
            $habituel = $this->habituel($historique[$joueur->getId()] ?? null, $historiqueGroupe ?? $moyenneGroupe);
            $lisse = ($joues * $parCombat + self::LISSAGE * $habituel) / ($joues + self::LISSAGE);

            $lignes[] = [
                'participation' => $p,
                'joueur' => $joueur,
                'rangActuel' => $rangActuel[$p->getId()],
                'trophees' => $p->getTrophees(),
                'joues' => $joues,
                'restants' => $restants,
                'parCombat' => round($lisse, 1),
                'habituel' => round($habituel, 1),
                'projetes' => (int) round($p->getTrophees() + $restants * $lisse),
                'attaque' => $attaque[$joueur->getId()] ?? null,
                'defense' => $defense[$joueur->getId()] ?? null,
            ];
        }

        usort($lignes, static fn (array $a, array $b) => [$b['projetes'], $b['trophees']] <=> [$a['projetes'], $a['trophees']]);
        foreach ($lignes as $i => &$ligne) {
            $ligne['rangPredit'] = $i + 1;
            $ligne['mouvement'] = $ligne['rangActuel'] - $ligne['rangPredit'];
        }

        return $lignes;
    }

    /**
     * Trophées cumulés et combats possibles (combatsMax) sur les semaines passées de chaque membre : un rythme par
     * « combat disponible », qui ne dépend pas du nombre d'attaques réellement jouées (les défenses rapportent aussi).
     *
     * @return array{array<int, array{trophees: int, combats: int}>, ?float} rythmes par joueur, rythme du groupe
     */
    private function rythmesHistoriques(GroupeLigue $groupe): array
    {
        $lignes = $this->em->createQuery(
            'SELECT IDENTITY(r.joueur) AS joueur, SUM(r.trophees) AS trophees, SUM(r.combatsMax) AS combats
             FROM '.ResultatSemaine::class.' r
             WHERE r.joueur IN (SELECT mj.id FROM '.Participation::class.' mp JOIN mp.joueur mj WHERE mp.groupe = :groupe)
             GROUP BY r.joueur'
        )->setParameter('groupe', $groupe)->getArrayResult();

        $parJoueur = [];
        $trophees = $combats = 0;
        foreach ($lignes as $l) {
            $parJoueur[(int) $l['joueur']] = ['trophees' => (int) $l['trophees'], 'combats' => (int) $l['combats']];
            $trophees += (int) $l['trophees'];
            $combats += (int) $l['combats'];
        }

        return [$parJoueur, $combats > 0 ? $trophees / $combats : null];
    }

    /** Trophées par combat habituels d'un joueur, tirés vers ceux du groupe s'il a peu d'historique. */
    private function habituel(?array $historique, float $groupe): float
    {
        if (!$historique || $historique['combats'] <= 0) {
            return $groupe;
        }

        return ($historique['trophees'] + self::LISSAGE_HISTORIQUE * $groupe) / ($historique['combats'] + self::LISSAGE_HISTORIQUE);
    }

    /** Nombre de combats de la dernière semaine connue de ce joueur (ResultatSemaine). */
    private function combatsMax(int $joueurId): ?int
    {
        $resultat = $this->em->getRepository(ResultatSemaine::class)->findOneBy(['joueur' => $joueurId], ['saisonId' => 'DESC']);

        return $resultat?->getCombatsMax();
    }
}
