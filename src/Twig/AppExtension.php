<?php

namespace App\Twig;

use App\Entity\Ligue;
use App\Service\TroupeCatalogue;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    /** Dégradé (clair, foncé) par famille de ligue, repris de l'ancien projet. */
    private const ACCENTS = [
        'legend' => ['#b888ff', '#ffd23a'], 'electro' => ['#86f2ff', '#22c8ff'], 'dragon' => ['#ff8fb6', '#ff4d6d'],
        'titan' => ['#6ad1ff', '#2d7fff'], 'p.e.k.k.a' => ['#c98bff', '#8b5cf6'], 'golem' => ['#76e8ff', '#2bb8ff'],
        'witch' => ['#ffdd75', '#ffb01f'], 'valkyrie' => ['#ffb888', '#e07b3a'], 'wizard' => ['#e3ecfa', '#9fb3cf'],
        'archer' => ['#85f5b3', '#36dc84'], 'barbarian' => ['#ffd2a1', '#ff9f43'], 'skeleton' => ['#d6dcee', '#8b97b8'],
    ];

    /** Nombre de colonnes (et de lignes) de la planche d'icônes public/img/sprite.webp. */
    private const PLANCHE = 13;

    /** @var array<string, int>|null */
    private ?array $planche = null;

    public function __construct(
        private TroupeCatalogue $catalogue,
        #[Autowire('%kernel.project_dir%/data/sprite.json')] private string $fichierPlanche,
    ) {
    }

    /**
     * Position d'une icône dans la planche unique (une seule requête pour toutes les images de troupes) ;
     * null si l'icône est inconnue (le gabarit affiche alors des initiales).
     *
     * @return array{cle: string, style: string}|null
     */
    #[AsTwigFunction('icone_planche')]
    public function iconePlanche(string $nom): ?array
    {
        $this->planche ??= json_decode((string) file_get_contents($this->fichierPlanche), true, 512, \JSON_THROW_ON_ERROR);
        $cle = str_starts_with($nom, 'th') && ctype_digit(substr($nom, 2)) ? $nom : TroupeCatalogue::slug($nom);
        if (!isset($this->planche[$cle]) || (!str_starts_with($cle, 'th') && null === $this->catalogue->image($nom))) {
            return null;
        }
        $i = $this->planche[$cle];

        return ['cle' => $cle, 'style' => '--c:'.($i % self::PLANCHE).';--r:'.intdiv($i, self::PLANCHE)];
    }

    #[AsTwigFunction('troupe_image')]
    public function troupeImage(string $nom): ?string
    {
        return $this->catalogue->image($nom);
    }

    /**
     * « Dragon + Balloon » -> ['Dragon', 'Balloon'].
     *
     * @return list<string>
     */
    #[AsTwigFilter('archetype_noms')]
    public function archetypeNoms(?string $archetype): array
    {
        return $archetype ? array_map('trim', explode('+', $archetype)) : [];
    }

    /**
     * @return array{string, string}
     */
    #[AsTwigFunction('ligue_accent')]
    public function accent(?Ligue $ligue): array
    {
        $nom = strtolower($ligue?->getNom() ?? '');
        foreach (self::ACCENTS as $cle => $couleurs) {
            if (str_contains($nom, $cle)) {
                return $couleurs;
            }
        }

        return ['#ffdd75', '#ffb01f'];
    }
}
