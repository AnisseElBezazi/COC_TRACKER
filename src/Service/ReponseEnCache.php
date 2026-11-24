<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Réponse déjà connue (lue en cache) : se lit comme une réponse HTTP, sans aucun appel réseau.
 */
final readonly class ReponseEnCache implements ResponseInterface
{
    /** @param array<string, mixed> $donnees */
    public function __construct(private array $donnees)
    {
    }

    public function getStatusCode(): int
    {
        return 200;
    }

    public function getHeaders(bool $throw = true): array
    {
        return ['content-type' => ['application/json']];
    }

    public function getContent(bool $throw = true): string
    {
        return json_encode($this->donnees, \JSON_THROW_ON_ERROR);
    }

    public function toArray(bool $throw = true): array
    {
        return $this->donnees;
    }

    public function cancel(): void
    {
    }

    public function getInfo(?string $type = null): mixed
    {
        return null === $type ? [] : null;
    }
}
