<?php

namespace App\Controller;

use App\Entity\Joueur;
use App\Entity\Participation;
use App\Entity\ResultatSemaine;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de sélection de ligue : les semaines d'un joueur, avec le logo de la ligue jouée.
 */
class LiguesController extends AbstractController
{
    #[Route('/ligues/{tag}', name: 'app_ligues', requirements: ['tag' => '[0289PYLQGRJCUVpylqgrjcuv]{3,14}'], methods: ['GET'])]
    public function selection(string $tag, EntityManagerInterface $em): Response
    {
        $joueur = $em->getRepository(Joueur::class)->findOneBy(['tag' => ImportService::normaliserTag($tag)])
            ?? throw $this->createNotFoundException();

        $participations = $em->createQuery('SELECT p, g FROM '.Participation::class.' p JOIN p.groupe g WHERE p.joueur = :j ORDER BY g.saisonId DESC')
            ->setParameter('j', $joueur)->getResult();

        $semaines = [];
        $saisonsAvecGroupe = [];
        foreach ($participations as $participation) {
            $groupe = $participation->getGroupe();
            $saisonsAvecGroupe[] = $groupe->getSaisonId();
            $rang = 1 + (int) $em->createQuery('SELECT COUNT(p.id) FROM '.Participation::class.' p WHERE p.groupe = :g AND p.trophees > :t')
                ->setParameters(['g' => $groupe, 't' => $participation->getTrophees()])->getSingleScalarResult();
            $taille = $groupe->getParticipations()->count();
            $semaines[] = [
                'groupe' => $groupe,
                'participation' => $participation,
                'rang' => $rang,
                'taille' => $taille,
                // 100 = premier du groupe, 0 = dernier : alimente l'anneau de la carte.
                'niveau' => $taille > 1 ? (int) round(100 * ($taille - $rang) / ($taille - 1)) : 100,
            ];
        }

        // Semaines terminées dont le groupe n'est plus consultable : on montre le résultat final seulement.
        $historique = array_values(array_filter(
            $em->getRepository(ResultatSemaine::class)->findBy(['joueur' => $joueur], ['saisonId' => 'DESC']),
            static fn (ResultatSemaine $r) => !\in_array($r->getSaisonId(), $saisonsAvecGroupe, true),
        ));

        $classements = array_merge(
            array_map(static fn (ResultatSemaine $r) => $r->getClassement(), $historique),
            array_map(static fn (array $s) => $s['rang'], $semaines),
        );
        $trophees = array_merge(
            array_map(static fn (ResultatSemaine $r) => $r->getTrophees(), $historique),
            array_map(static fn (array $s) => $s['participation']->getTrophees(), $semaines),
        );

        return $this->render('ligues.html.twig', [
            'joueur' => $joueur,
            'semaines' => $semaines,
            'historique' => $historique,
            'meilleurRang' => $classements ? min($classements) : null,
            'meilleurScore' => $trophees ? max($trophees) : null,
            'nombreSemaines' => \count($classements),
        ]);
    }
}
