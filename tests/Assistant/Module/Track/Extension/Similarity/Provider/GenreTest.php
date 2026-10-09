<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\SimilarityTestTrack;
use PHPUnit\Framework\TestCase;

// AI: testy nie mają własnego autoloadera, a fabryka utworu jest współdzielona przez kilka testów modułu
require_once __DIR__ . '/../SimilarityTestTrack.php';

final class GenreTest extends TestCase
{
    public function testFullRangeIncludesBaseGenre(): void
    {
        $provider = new Genre([ 'min_similarity' => 0, 'max_similarity' => 100 ]);

        $genres = $provider->getCriteria(SimilarityTestTrack::create(genre: 'House'));

        self::assertContains('House', $genres);
        self::assertContains('Tech House', $genres);
    }

    public function testSimilarityRangeSelectsNeighbouringGenres(): void
    {
        $provider = new Genre([ 'min_similarity' => 60, 'max_similarity' => 80 ]);

        $genres = $provider->getCriteria(SimilarityTestTrack::create(genre: 'House'));

        self::assertContains('Electro House', $genres);
        self::assertContains('Techno', $genres);
        self::assertNotContains('House', $genres);
        self::assertNotContains('Tech House', $genres);
        self::assertNotContains('Electronic', $genres);
    }

    public function testUnknownGenreDoesNotNarrowCandidates(): void
    {
        $provider = new Genre([ 'min_similarity' => 60, 'max_similarity' => 80 ]);

        self::assertNull($provider->getCriteria(SimilarityTestTrack::create(genre: 'Hardstyle')));
    }
}
