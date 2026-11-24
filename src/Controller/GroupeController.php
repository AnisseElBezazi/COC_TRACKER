<?php

namespace App\Controller;

use App\Dto\CompositionsRequete;
use App\Entity\GroupeLigue;
use App\Service\GroupeStats;
use App\Service\Prediction;
use App\Service\ArmyDecoder;
use App\Service\ImportService;
use App\Service\MetaVue;
use App\Service\ReglesLigue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class GroupeController extends AbstractController
{
    #[Route('/groupe/{id}', name: 'app_groupe', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function voir(GroupeLigue $groupe, Prediction $prediction, GroupeStats $stats, ImportService $import, ArmyDecoder $decodeur, ReglesLigue $regles, MetaVue $metaVue): Response
    {
        $defenses = $stats->defenseParArchetype($groupe);
        $lignes = $prediction->predire($groupe);
        // Les zones de promotion et de relégation dépendent de la ligue (pourcentage du groupe, ou top N en Légende).
        $regle = $regles->pour($groupe->getLigue(), \count($lignes));
        $equipements = $this->parHero($stats->archetypesEquipement($groupe));

        return $this->render('groupe/voir.html.twig', [
            'groupe' => $groupe,
            'analyseIncomplete' => $import->analyseIncomplete($groupe),
            // Au-delà des premiers du classement : analysés seulement si l'utilisateur le demande (bouton).
            'autresAAnalyser' => $import->autresAAnalyser($groupe),
            'prediction' => $lignes,
            'regle' => $regle,
            'kpis' => $this->kpis($lignes, $regle),
            'scorers' => $stats->topScorers($groupe, 15),
            'metaTroupes' => $stats->meta($groupe, 'troupes'),
            'metaHeros' => $stats->meta($groupe, 'heros'),
            'affrontes' => $stats->meta($groupe, 'troupes', 10, 'defenseur'),
            'heros' => $stats->usage($groupe, 'heros'),
            'familiers' => $stats->usage($groupe, 'familier'),
            'equipements' => $stats->equipementsParHero($groupe),
            'defenses' => $defenses,
            'favoris' => $stats->favoris($groupe),
            'armees' => array_map(static fn (array $a) => ['armee' => $decodeur->decoder($a['code'])] + $a, $stats->armeesFavorites($groupe)),
            'equipementsArchetypes' => $equipements,
            // La méta sous trois présentations : tier list, carte popularité × réussite, tableau.
            'metaVues' => [
                'troupes' => $metaVue->construire($stats->meta($groupe, 'troupes'), 'troupes'),
                'heros' => $metaVue->construire($stats->meta($groupe, 'heros'), 'heros'),
                'equipements' => $metaVue->construire($equipements['Tous'] ?? [], 'equipements'),
            ],
        ]);
    }

    /**
     * Les compositions exactes (armées) rattachées à un archétype de la méta : un fragment HTML chargé au clic,
     * pour ne pas alourdir la page avec les centaines de variantes de tous les archétypes.
     */
    #[Route('/groupe/{id}/compositions', name: 'app_groupe_compositions', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function compositions(GroupeLigue $groupe, #[MapQueryString] CompositionsRequete $requete, GroupeStats $stats, ArmyDecoder $decodeur): Response
    {
        $resultat = $stats->compositionsDe($groupe, $requete->famille, $requete->nom, $requete->hero, $requete->tri);

        return $this->render('groupe/_compositions.html.twig', [
            'requete' => $requete,
            'total' => $resultat['total'],
            'distinctes' => $resultat['distinctes'],
            'variantes' => array_map(static fn (array $v) => $v + ['armee' => $decodeur->decoder($v['code'])], $resultat['variantes']),
        ]);
    }

    /**
     * Importe les combats des membres du groupe par paquets de 10, pour afficher une progression
     * (l'import des 100 membres dure plus d'une minute : trop long pour une seule requête).
     */
    #[Route('/groupe/{id}/analyser', name: 'app_groupe_analyser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function analyser(GroupeLigue $groupe, Request $request, ImportService $import): JsonResponse
    {
        if (!$this->isCsrfTokenValid('analyser', (string) $request->request->get('_token'))) {
            return new JsonResponse(['erreur' => 'Jeton invalide'], 403);
        }

        // Sans « tout », seuls les premiers du classement sont analysés ; avec, le reste du groupe aussi (bouton de l'écran).
        $limite = $request->request->getBoolean('tout') ? null : ImportService::PREMIERS;
        [$fait, $total] = $import->importerMembres($groupe, max(0, $request->request->getInt('debut')), limite: $limite);

        return new JsonResponse([
            'fait' => $fait,
            'total' => $total,
            'termine' => $fait >= $total,
            // Joueurs que l'API a refusés temporairement : l'écran repasse dessus.
            'restants' => $fait >= $total ? $import->restantsAAnalyser($groupe, $limite) : null,
        ]);
    }

    /**
     * Classements d'archétypes d'équipement : un classement général puis un par héros.
     *
     * @param list<array<string, mixed>> $lignes
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function parHero(array $lignes): array
    {
        $listes = ['Tous' => $lignes];
        foreach ($lignes as $ligne) {
            $listes[$ligne['hero']][] = $ligne;
        }
        $heros = array_diff_key($listes, ['Tous' => 0]);
        ksort($heros);

        return ['Tous' => $listes['Tous']] + $heros;
    }

    /**
     * Chiffres clés du bandeau, calculés sur la projection du groupe.
     *
     * @param list<array<string, mixed>>                                         $lignes
     * @param array{nom: string, promus: int, relegues: int, source: string}|null $regle règle de promotion de la ligue, si connue
     *
     * @return array<string, mixed>
     */
    private function kpis(array $lignes, ?array $regle): array
    {
        $joues = array_sum(array_column($lignes, 'joues'));
        $possibles = $joues + array_sum(array_column($lignes, 'restants'));
        $echantillon = $etoiles = $pourcentage = 0.0;
        foreach ($lignes as $ligne) {
            $n = $ligne['attaque']['echantillon'] ?? 0;
            $echantillon += $n;
            $etoiles += $n * ($ligne['attaque']['etoilesMoyennes'] ?? 0);
            $pourcentage += $n * ($ligne['attaque']['pourcentageMoyen'] ?? 0);
        }

        return [
            'joueurs' => \count($lignes),
            'finis' => \count(array_filter($lignes, static fn (array $l) => 0 === $l['restants'])),
            'joues' => $joues,
            'avancement' => $possibles > 0 ? (int) round(100 * $joues / $possibles) : 0,
            'etoiles' => $echantillon > 0 ? $etoiles / $echantillon : null,
            'pourcentage' => $echantillon > 0 ? $pourcentage / $echantillon : null,
            'tropheesParCombat' => $joues > 0 ? array_sum(array_column($lignes, 'trophees')) / $joues : null,
            // Trophées prévus du dernier joueur promu, et du dernier joueur encore à l'abri de la relégation.
            'seuilPromotion' => $regle && $regle['promus'] > 0 ? ($lignes[$regle['promus'] - 1]['projetes'] ?? null) : null,
            'seuilRelegation' => $regle && $regle['relegues'] > 0 ? ($lignes[\count($lignes) - $regle['relegues'] - 1]['projetes'] ?? null) : null,
        ];
    }
}
