<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Réponse HTTP dont le contenu est rangé en cache dès qu'on le lit avec toArray() :
 * la requête reste lancée en parallèle avec les autres, et la suivante se fera sans réseau.
 */
final class ReponseMemorisee implements ResponseInterface
{
    public function __construct(
        private readonly ResponseInterface $reponse,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cle,
        private readonly int $duree,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->reponse->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->reponse->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        return $this->reponse->getContent($throw);
    }

    public function toArray(bool $throw = true): array
    {
        $donnees = $this->reponse->toArray($throw);
        // toArray() a levé une exception si la réponse était une erreur : seules les réponses réussies arrivent ici.
        $item = $this->cache->getItem($this->cle)->set($donnees)->expiresAfter($this->duree);
        $this->cache->save($item);

        return $donnees;
    }

    public function cancel(): void
    {
        $this->reponse->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->reponse->getInfo($type);
    }
}
