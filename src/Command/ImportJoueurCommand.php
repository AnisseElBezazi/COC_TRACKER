<?php

namespace App\Command;

use App\Service\ImportService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-joueur', description: 'Importe un joueur (et, avec --membres, tout son groupe de ligue) depuis l\'API Clash of Clans')]
class ImportJoueurCommand
{
    public function __construct(private ImportService $import)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Tag du joueur, ex. "#82U22QLL"')]
        string $tag,
        #[Option(description: 'Importe aussi les combats de tous les membres de son groupe')]
        bool $membres = false,
        #[Option(description: 'Nombre maximum de membres à importer avec --membres')]
        int $limite = 100,
    ): int {
        $joueur = $this->import->importerJoueur($tag);
        $io->success(\sprintf('%s (%s) importé.', $joueur->getNom(), $joueur->getTag()));

        if ($membres) {
            $groupes = $this->import->groupesDuJoueur($joueur);
            $io->progressStart($limite);
            foreach (\array_slice($groupes, 0, 1) as $groupe) {
                // Jusqu'à 3 passages : l'API peut refuser temporairement certains joueurs (trop de requêtes).
                for ($passage = 1; $passage <= 3; ++$passage) {
                    $debut = 0;
                    do {
                        [$debut, $total] = $this->import->importerMembres($groupe, $debut);
                        $io->progressAdvance(10);
                    } while ($debut < $total && $debut < $limite);
                    if (0 === $this->import->restantsAAnalyser($groupe)) {
                        break;
                    }
                    sleep(2);
                }
                if (($restants = $this->import->restantsAAnalyser($groupe)) > 0) {
                    $io->warning(\sprintf('%d joueur(s) n\'ont pas pu être analysés (API saturée). Relance la commande pour reprendre.', $restants));
                }
            }
            $io->progressFinish();
        }

        if ($this->import->ignores() > 0) {
            $io->note(\sprintf('%d entité(s) invalide(s) ignorée(s).', $this->import->ignores()));
        }

        return 0;
    }
}
