<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use KeyTools\KeyTools;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class MusicalKeyTest extends TestCase
{
    use DeprecationGuard;

    private const int RELATED_KEYS_COUNT = 8;

    private MusicalKey $provider;

    protected function setUp(): void
    {
        $this->provider = new MusicalKey();
    }

    /**
     * Wyniki ośmiu transformacji są kluczami tablicy, więc kolizja dwóch transformacji cicho
     * nadpisałaby wcześniejszą wartość.
     */
    public function testEveryCamelotKeyHasEightDistinctRelatedKeys(): void
    {
        $similarityMap = $this->getSimilarityMap();

        self::assertCount(count(KeyTools::NOTATION_KEYS_CAMELOT_KEY), $similarityMap);

        foreach (KeyTools::NOTATION_KEYS_CAMELOT_KEY as $keyCode) {
            self::assertCount(
                self::RELATED_KEYS_COUNT,
                $similarityMap[$keyCode],
                sprintf('Tonacja "%s" ma mniej wpisów, niż jest transformacji', $keyCode),
            );
        }
    }

    public function testSimilarityMapForSingleKey(): void
    {
        self::assertSame(
            [ '8A' => 100, '8B' => 95, '7A' => 95, '9A' => 95, '9B' => 65, '5A' => 65, '10A' => 65, '3A' => 65 ],
            $this->getSimilarityMap()['8A'],
        );
    }

    /** @dataProvider keyPairs */
    public function testSimilarityValueForKeyPair(?string $baseKey, ?string $comparedKey, int $expected): void
    {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(initialKey: $baseKey),
            TrackFactory::create(initialKey: $comparedKey),
        );

        self::assertSame($expected, $similarity);
    }

    public function testCriteriaContainsBaseKeyAndRelatedKeys(): void
    {
        $initialKeys = $this->provider->getCriteria(TrackFactory::create(initialKey: '8A'));

        self::assertCount(self::RELATED_KEYS_COUNT, $initialKeys);
        self::assertContains('8A', $initialKeys);
    }

    /** @dataProvider keysWithoutCriteria */
    public function testCriteriaIsNullForKeyOutsideNotation(?string $initialKey): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(initialKey: $initialKey)));
    }

    public static function keyPairs(): iterable
    {
        yield 'ta sama tonacja' => [ '8A', '8A', 100 ];
        yield 'tonacja równoległa' => [ '8A', '8B', 95 ];
        yield 'kwarta czysta' => [ '8A', '7A', 95 ];
        yield 'kwinta czysta' => [ '8A', '9A', 95 ];
        yield 'cały ton' => [ '8A', '10A', 65 ];
        yield 'półton' => [ '8A', '3A', 65 ];
        yield 'tonacja niepowiązana' => [ '8A', '2B', 0 ];
        yield 'tonacja poza notacją' => [ '08A', '8A', 0 ];
        yield 'notacja muzyczna' => [ 'Am', '8A', 0 ];
        yield 'brak tonacji bazowej' => [ null, '8A', 0 ];
        yield 'brak tonacji porównywanej' => [ '8A', null, 0 ];
    }

    public static function keysWithoutCriteria(): iterable
    {
        yield 'tonacja z wiodącym zerem' => [ '08A' ];
        yield 'notacja muzyczna' => [ 'Am' ];
        yield 'brak tonacji' => [ null ];
    }

    private function getSimilarityMap(): array
    {
        return (new ReflectionProperty(MusicalKey::class, 'similarityMap'))->getValue($this->provider);
    }
}
