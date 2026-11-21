<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ArchetypeFavori;
use App\Entity\Joueur;
use App\Repository\CombatRepository;
use App\Repository\JoueurRepository;

class ArchetypeFavoriProvider implements ProviderInterface
{
    public function __construct(
        private JoueurRepository $joueurRepository,
        private CombatRepository $combatRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ArchetypeFavori
    {
        $joueur = $this->joueurRepository->find($uriVariables['id']);
        if ($joueur === null) {
            return null;
        }

        $archetypeFavori = new ArchetypeFavori();
        $archetypeFavori->id = $joueur->getId();
        $archetypeFavori->archetypeTroupes = $this->plusJoue($joueur, 'co.archetypeTroupes');
        $archetypeFavori->archetypeHeros = $this->plusJoue($joueur, 'co.archetypeHeros');
        $archetypeFavori->attaques = $this->combatRepository->count(['attaquant' => $joueur]);

        return $archetypeFavori;
    }

    private function plusJoue(Joueur $joueur, string $archetype): ?string
    {
        $resultat = $this->combatRepository->createQueryBuilder('c')
            ->select($archetype.' AS archetype, COUNT(c.id) AS attaques')
            ->join('c.composition', 'co')
            ->where('c.attaquant = :joueur')
            ->andWhere($archetype.' IS NOT NULL')
            ->setParameter('joueur', $joueur)
            ->groupBy('archetype')
            ->orderBy('attaques', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $resultat['archetype'] ?? null;
    }
}
