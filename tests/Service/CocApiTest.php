<?php

namespace App\Tests\Service;

use App\Service\CocApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CocApiTest extends TestCase
{
    public function testUneMemeRequeteLueDeuxFoisNEstFaiteQuUneFois(): void
    {
        $envoyees = [];
        $client = new MockHttpClient(static function (string $methode, string $url) use (&$envoyees) {
            $envoyees[] = $url;

            return new MockResponse(json_encode(['items' => [['id' => 1, 'name' => 'Dragon League']]]), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $api = new CocApi('https://api.test/v1', $client, new ArrayAdapter());

        $premiere = $api->ligues();
        $deuxieme = $api->ligues();

        self::assertSame($premiere, $deuxieme);
        self::assertCount(1, $envoyees, 'la deuxième lecture vient du cache, sans appel réseau');
    }

    public function testLesReponsesEnParalleleSontRangeesEnCacheQuandOnLesLit(): void
    {
        $envoyees = 0;
        $client = new MockHttpClient(static function () use (&$envoyees) {
            ++$envoyees;

            return new MockResponse(json_encode(['name' => 'Alice', 'items' => []]), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $api = new CocApi('https://api.test/v1', $client, new ArrayAdapter());

        foreach ($api->demanderJoueur('#2PP') as $reponse) {
            $reponse->toArray();
        }
        self::assertSame(3, $envoyees);

        foreach ($api->demanderJoueur('#2PP') as $reponse) {
            self::assertSame('Alice', $reponse->toArray()['name']);
        }
        self::assertSame(3, $envoyees, 'profil, historique et combats sont relus depuis le cache');
    }

    public function testUneErreurDeLApiNEstJamaisMiseEnCache(): void
    {
        $envoyees = 0;
        $client = new MockHttpClient(static function () use (&$envoyees) {
            return 1 === ++$envoyees
                ? new MockResponse('{}', ['http_code' => 404, 'response_headers' => ['content-type' => 'application/json']])
                : new MockResponse(json_encode(['name' => 'Alice']), ['response_headers' => ['content-type' => 'application/json']]);
        });
        $api = new CocApi('https://api.test/v1', $client, new ArrayAdapter());

        try {
            $api->joueur('#2PP');
            self::fail('une 404 doit lever une exception');
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
        }

        self::assertSame('Alice', $api->joueur('#2PP')['name']);
        self::assertSame(2, $envoyees);
    }
}
