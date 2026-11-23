<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\ArchetypeFavoriProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/joueurs/{id}/archetype-favori',
            provider: ArchetypeFavoriProvider::class,
        ),
    ],
)]
class ArchetypeFavori
{
    public int $id;

    public ?string $archetypeTroupes = null;

    public ?string $archetypeHeros = null;

    public int $attaques = 0;
}
