<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\CombatRepository;

class MetaProvider implements ProviderInterface
{
    public function __construct(
        private CombatRepository $combatRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $archetype = 'co.archetypeTroupes';
        if (($context['filters']['par'] ?? '') === 'heros') {
            $archetype = 'co.archetypeHeros';
        }

        $query = $this->combatRepository->createQueryBuilder('c')
            ->select($archetype.' AS archetype, COUNT(c.id) AS combats, AVG(CASE WHEN c.etoiles = 3 THEN 100 ELSE 0 END) AS tauxTroisEtoiles, AVG(c.etoiles) AS etoilesMoyennes, AVG(c.pourcentage) AS pourcentageMoyen')
            ->join('c.composition', 'co')
            ->where($archetype.' IS NOT NULL')
            ->groupBy('archetype')
            ->having('COUNT(c.id) >= 20')
            ->orderBy('tauxTroisEtoiles', 'DESC')
            ->addOrderBy('etoilesMoyennes', 'DESC')
            ->addOrderBy('pourcentageMoyen', 'DESC');

        if (isset($context['filters']['ligue'])) {
            $query->join('c.groupe', 'g')
                ->andWhere('g.ligue = :ligue')
                ->setParameter('ligue', $context['filters']['ligue']);
        }

        return $query->getQuery()->getArrayResult();
    }
}
