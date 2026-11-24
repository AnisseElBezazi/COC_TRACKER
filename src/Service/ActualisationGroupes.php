<?php

namespace App\Service;

use App\Entity\GroupeLigue;
use App\Entity\Participation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Rafraîchit en arrière-plan les groupes de la semaine en cours (pensé pour une tâche planifiée) :
 * les pages n'ont alors plus à appeler l'API Clash, elles lisent ce qui est déjà en base.
 * Seuls les premiers du classement (ImportService::PREMIERS) sont suivis ; chaque passage ne redemande que ceux qui ont attaqué depuis.
 */
class ActualisationGroupes
{
    /** Passages sur un même groupe : l'API peut refuser temporairement certains joueurs (429). */
    private const PASSAGES = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private ImportService $import,
    ) {
    }

    /**
     * @return array{groupes: int, restants: int} groupes traités, et joueurs que l'API n'a pas pu servir cette fois
     */
    public function actualiser(?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable();
        $groupes = $this->em->createQuery('SELECT g FROM '.GroupeLigue::class.' g WHERE g.dateDebut <= :maintenant AND g.dateFin >= :maintenant')
            ->setParameter('maintenant', $maintenant)->getResult();

        $restants = 0;
        foreach ($groupes as $groupe) {
            try {
                $this->actualiserGroupe($groupe);
            } catch (ExceptionInterface) {
                // API injoignable : on garde les données connues et on réessaiera au prochain passage planifié.
            }
            $restants += $this->import->restantsAAnalyser($groupe, ImportService::PREMIERS);
        }

        return ['groupes' => \count($groupes), 'restants' => $restants];
    }

    private function actualiserGroupe(GroupeLigue $groupe): void
    {
        // Un membre de référence (le mieux classé) : son profil donne le groupe, dont la réponse met à jour tous les membres.
        $reference = $this->em->createQuery('SELECT p, j FROM '.Participation::class.' p JOIN p.joueur j WHERE p.groupe = :g ORDER BY p.trophees DESC')
            ->setParameter('g', $groupe)->setMaxResults(1)->getOneOrNullResult();
        if ($reference) {
            $this->import->importerJoueur($reference->getJoueur()->getTag());
        }

        for ($passage = 1; $passage <= self::PASSAGES; ++$passage) {
            $debut = 0;
            do {
                [$debut, $total] = $this->import->importerMembres($groupe, $debut, limite: ImportService::PREMIERS);
            } while ($debut < $total);
            if (0 === $this->import->restantsAAnalyser($groupe, ImportService::PREMIERS)) {
                return;
            }
            sleep(2);
        }
    }
}
