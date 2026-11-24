<?php

namespace App\Controller;

use App\Entity\Joueur;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    public function accueil(): Response
    {
        return $this->render('accueil.html.twig');
    }

    /**
     * Un seul champ : le tag. Le joueur et son groupe sont importés depuis l'API (règle E du mapping),
     * puis on arrive sur la sélection de ligue.
     */
    #[Route('/rechercher', name: 'app_rechercher', methods: ['POST'])]
    public function rechercher(Request $request, EntityManagerInterface $em, ImportService $import): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('rechercher', (string) $request->request->get('_token'))) {
            $this->addFlash('erreur', 'Formulaire expiré, réessaie.');

            return $this->redirectToRoute('app_accueil');
        }

        $tag = ImportService::normaliserTag((string) $request->request->get('tag'));
        if (!preg_match(Joueur::TAG_REGEX, $tag)) {
            $this->addFlash('erreur', 'Ce tag n\'est pas valide (lettres PYLQGRJCUV et chiffres 0289).');

            return $this->redirectToRoute('app_accueil');
        }

        // Joueur analysé il y a moins de 10 minutes : on réutilise ses données (aucun appel à l'API).
        $connu = $em->getRepository(Joueur::class)->findOneBy(['tag' => $tag]);
        $frais = $connu?->getImporteLe() && $connu->getImporteLe() > new \DateTimeImmutable('-10 minutes');

        try {
            if (!$frais) {
                $import->importerJoueur($tag);
            }
        } catch (\Throwable) {
            // L'API est peut-être indisponible : si on connaît déjà ce joueur, on continue avec ses données.
            if (!$em->getRepository(Joueur::class)->findOneBy(['tag' => $tag])) {
                $this->addFlash('erreur', 'Joueur introuvable : vérifie le tag, ou réessaie dans un instant.');

                return $this->redirectToRoute('app_accueil');
            }
        }

        return $this->redirectToRoute('app_ligues', ['tag' => ltrim($tag, '#')]);
    }
}
