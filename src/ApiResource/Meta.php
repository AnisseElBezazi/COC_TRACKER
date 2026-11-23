<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\MetaProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/meta',
            paginationEnabled: false,
            provider: MetaProvider::class,
            parameters: [
                'par' => new QueryParameter(description: 'troupes (par défaut) ou heros'),
                'ligue' => new QueryParameter(description: 'id de la ligue, ex. 30'),
            ],
        ),
    ],
)]
class Meta
{
    public string $archetype;

    public int $combats;

    public float $tauxTroisEtoiles;

    public float $etoilesMoyennes;

    public float $pourcentageMoyen;
}
