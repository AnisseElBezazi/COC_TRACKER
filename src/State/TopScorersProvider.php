<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TopScorers;
use App\Entity\Participation;
use App\Repository\CombatRepository;
use App\Repository\GroupeLigueRepository;

class TopScorersProvider implements ProviderInterface
{
    public function __construct(
        private GroupeLigueRepository $groupeRepository,
        private CombatRepository $combatRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TopScorers
    {
        $groupe = $this->groupeRepository->find($uriVariables['id']);
        if ($groupe === null) {
            return null;
        }

        $topScorers = new TopScorers();
        $topScorers->id = $groupe->getId();

        $topScorers->attaquants = $this->combatRepository->createQueryBuilder('c')
            ->select('a.tag, a.nom, SUM(c.trophees) AS trophees, AVG(c.etoiles) AS etoilesMoyennes, COUNT(c.id) AS attaques')
            ->join('c.attaquant', 'a')
            ->where('c.groupe = :groupe')
            ->setParameter('groupe', $groupe)
            ->groupBy('a.id')
            ->orderBy('trophees', 'DESC')
            ->addOrderBy('etoilesMoyennes', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $topScorers->defenseurs = $this->combatRepository->createQueryBuilder('c')
            ->select('d.tag, d.nom, p.defensesGagnees, SUM(c.etoiles) AS etoilesConcedees, COUNT(c.id) AS defenses')
            ->join('c.defenseur', 'd')
            ->join(Participation::class, 'p', 'WITH', 'p.joueur = d AND p.groupe = c.groupe')
            ->where('c.groupe = :groupe')
            ->setParameter('groupe', $groupe)
            ->groupBy('d.id, p.defensesGagnees')
            ->orderBy('p.defensesGagnees', 'DESC')
            ->addOrderBy('etoilesConcedees', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        return $topScorers;
    }
}
