<?php

namespace App\Controller;

use App\Entity\GroupeLigue;
use App\Entity\Joueur;
use App\Entity\Participation;
use App\Service\ArmyDecoder;
use App\Service\GroupeStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JoueurController extends AbstractController
{
    #[Route('/groupe/{groupe}/joueur/{joueur}', name: 'app_joueur', requirements: ['groupe' => '\d+', 'joueur' => '\d+'], methods: ['GET'])]
    public function voir(GroupeLigue $groupe, Joueur $joueur, GroupeStats $stats, EntityManagerInterface $em, ArmyDecoder $decodeur): Response
    {
        $participation = $em->getRepository(Participation::class)->findOneBy(['groupe' => $groupe, 'joueur' => $joueur])
            ?? throw $this->createNotFoundException();

        $favorite = $stats->armeesFavorites($groupe)[$joueur->getId()] ?? null;

        return $this->render('joueur/voir.html.twig', [
            'armee' => $favorite ? ['armee' => $decodeur->decoder($favorite['code'])] + $favorite : null,
            'groupe' => $groupe,
            'joueur' => $joueur,
            'participation' => $participation,
            'attaques' => $stats->attaquesDuJoueur($joueur->getId()),
            'herosJoues' => $stats->herosDuJoueur($joueur->getId()),
            'equipements' => $stats->equipementsDuJoueur($groupe, $joueur->getId()),
            'defense' => $stats->defenseParArchetype($groupe, $joueur->getId())[$joueur->getId()] ?? null,
        ]);
    }
}
