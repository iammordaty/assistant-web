<?php

namespace Assistant\Module\Search\Extension\Request;

use Assistant\Module\Search\Extension\Storage\Query;
use MongoDB\BSON\Regex;
use PHPUnit\Framework\TestCase;
use Slim\Http\Factory\DecoratedServerRequestFactory;
use GuzzleHttp\Psr7\HttpFactory;

final class SearchRequestTest extends TestCase
{
    public function testOrSplitsModifiersIntoMongoOr(): void
    {
        $storage = self::toStorage('a: James Harcourt or R:James Harcourt');

        self::assertArrayNotHasKey('artists', $storage);
        self::assertArrayNotHasKey('$text', $storage);
        self::assertCount(2, $storage['$or']);
        self::assertEquals([ 'artists' => new Regex('James Harcourt', 'i') ], $storage['$or'][0]);
        self::assertEquals(
            [ 'title' => new Regex('\\[[^\\]]*James Harcourt[^\\]]*\\]', 'i') ],
            $storage['$or'][1]
        );
    }

    public function testWithoutOrKeepsPlainCriteria(): void
    {
        $storage = self::toStorage('foo a: James Harcourt');

        self::assertArrayNotHasKey('$or', $storage);
        self::assertSame([ '$search' => 'foo' ], $storage['$text']);
        self::assertEquals(new Regex('James Harcourt', 'i'), $storage['artists']);
    }

    public function testOrInsideModifierValueIsNotSplit(): void
    {
        $storage = self::toStorage('t: Now or Never');

        self::assertArrayNotHasKey('$or', $storage);
        self::assertEquals(new Regex('^[^\\[]*Now or Never', 'i'), $storage['title']);
    }

    private static function toStorage(string $name): array
    {
        $request = (new DecoratedServerRequestFactory(new HttpFactory()))
            ->createServerRequest('GET', '/')
            ->withQueryParams([ 'name' => $name ]);

        return Query::fromSearchCriteria(SearchRequest::fromServerRequest($request)->toSearchCriteria())->toStorage();
    }
}
