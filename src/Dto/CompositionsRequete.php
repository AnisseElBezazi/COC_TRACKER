<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres de la liste des compositions d'un archétype (lus dans l'URL, validés avant d'arriver au contrôleur).
 */
final readonly class CompositionsRequete
{
    public function __construct(
        /** Famille d'archétypes : troupes, héros, ou paire d'équipements d'un héros. */
        #[Assert\Choice(choices: ['troupes', 'heros', 'equipements'])]
        public string $famille,
        /** Nom de l'archétype, ex. « Dragon + Balloon » ou « Giant Gauntlet + Spiky Ball ». */
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $nom,
        /** Héros porteur des équipements (famille « equipements » seulement). */
        #[Assert\Length(max: 60)]
        public ?string $hero = null,
        /** Tri : les plus jouées, ou le meilleur taux de 3 étoiles. */
        #[Assert\Choice(choices: ['jouees', 'taux'])]
        public string $tri = 'jouees',
    ) {
    }
}
