<?php

namespace App\Command;

use App\Service\ActualisationGroupes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Lancée toutes les 30 minutes par le Scheduler (worker : php bin/console messenger:consume scheduler_default),
 * ou à la main, ou par un cron système : « *​/30 * * * * php bin/console app:actualiser-groupes --no-debug ».
 */
#[AsCommand(name: 'app:actualiser-groupes', description: 'Rafraîchit les groupes de la semaine en cours (seuls les joueurs qui ont attaqué sont redemandés à l\'API)')]
#[AsCronTask('*/30 * * * *')]
class ActualiserGroupesCommand
{
    public function __construct(private ActualisationGroupes $actualisation)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $resultat = $this->actualisation->actualiser();
        $io->success(\sprintf('%d groupe(s) actualisé(s).', $resultat['groupes']));
        if ($resultat['restants'] > 0) {
            $io->warning(\sprintf('%d joueur(s) n\'ont pas pu être analysés (API saturée) : ils seront repris au prochain passage.', $resultat['restants']));
        }

        return 0;
    }
}
