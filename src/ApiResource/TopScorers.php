<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\TopScorersProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/groupe_ligues/{id}/top-scorers',
            provider: TopScorersProvider::class,
        ),
    ],
)]
class TopScorers
{
    public int $id;

    public array $attaquants = [];

    public array $defenseurs = [];
}
