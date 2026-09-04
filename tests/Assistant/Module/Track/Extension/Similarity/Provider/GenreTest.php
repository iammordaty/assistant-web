<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class GenreTest extends TestCase
{
    use DeprecationGuard;

    private Genre $provider;

    protected function setUp(): void
    {
        $this->provider = new Genre();
    }

    /** Para gatunków opisana dwa razy dwiema różnymi wartościami czyni jedną z nich kodem martwym */
    public function testSimilarityMapHasNoConflictingPairs(): void
    {
        $similarityValues = [];

        foreach ($this->getSimilarityMap() as [ $baseGenre, $comparedGenre, $similarity ]) {
            $pair = sprintf('%s -> %s', $baseGenre, $comparedGenre);

            self::assertSame(
                $similarityValues[$pair] ?? $similarity,
                $similarity,
                sprintf('Para "%s" ma dwie różne wartości podobieństwa', $pair),
            );

            $similarityValues[$pair] = $similarity;
        }
    }

    public function testSimilarityMapIsSymmetric(): void
    {
        $similarityValues = [];

        foreach ($this->getSimilarityMap() as [ $baseGenre, $comparedGenre, $similarity ]) {
            $similarityValues[sprintf('%s -> %s', $baseGenre, $comparedGenre)] = $similarity;
        }

        foreach ($this->getSimilarityMap() as [ $baseGenre, $comparedGenre, $similarity ]) {
            $pair = sprintf('%s -> %s', $comparedGenre, $baseGenre);

            self::assertSame($similarity, $similarityValues[$pair] ?? null, sprintf('Brak pary "%s"', $pair));
        }
    }

    /** @dataProvider genrePairs */
    public function testSimilarityValueForGenrePair(?string $baseGenre, ?string $comparedGenre, int $expected): void
    {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(genre: $baseGenre),
            TrackFactory::create(genre: $comparedGenre),
        );

        self::assertSame($expected, $similarity);
    }

    public function testCriteriaContainsBaseGenreWithoutDuplicates(): void
    {
        $genres = $this->provider->getCriteria(TrackFactory::create(genre: 'House'));

        self::assertContains('House', $genres);
        self::assertContains('Afro House', $genres);
        self::assertSame(array_values(array_unique($genres)), $genres);
        self::assertSame(range(0, count($genres) - 1), array_keys($genres));
    }

    /** @dataProvider genresWithoutCriteria */
    public function testCriteriaIsNullForGenreOutsideMap(?string $genre): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(genre: $genre)));
    }

    public static function genrePairs(): iterable
    {
        yield 'ten sam gatunek' => [ 'House', 'House', 100 ];
        yield 'gatunek nieobjęty mapą, ten sam' => [ 'Hardstyle', 'Hardstyle', 100 ];

        // para zdefiniowana w bloku Afro House; wcześniej sprzeczny wpis w bloku House dawał 55
        yield 'House -> Afro House' => [ 'House', 'Afro House', 90 ];
        yield 'Afro House -> House' => [ 'Afro House', 'House', 90 ];

        yield 'House -> Tech House' => [ 'House', 'Tech House', 90 ];
        yield 'Techno -> Deep House' => [ 'Techno', 'Deep House', 55 ];
        yield 'Deep House -> Techno' => [ 'Deep House', 'Techno', 55 ];

        yield 'gatunki bez wspólnego wpisu' => [ 'House', 'Hardstyle', 0 ];
        yield 'brak gatunku bazowego' => [ null, 'House', 0 ];
        yield 'brak gatunku porównywanego' => [ 'House', null, 0 ];
        yield 'brak obu gatunków' => [ null, null, 0 ];
        yield 'pusty gatunek' => [ '', 'House', 0 ];
    }

    public static function genresWithoutCriteria(): iterable
    {
        yield 'gatunek nieobjęty mapą' => [ 'Hardstyle' ];
        yield 'brak gatunku' => [ null ];
        yield 'pusty gatunek' => [ '' ];
    }

    private function getSimilarityMap(): array
    {
        return (new ReflectionProperty(Genre::class, 'similarityMap'))->getValue($this->provider);
    }
}
