<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MusicClassifierResultTest extends TestCase
{
    /**
     * Wektor cech to prawdopodobieństwo jednej, ustalonej klasy każdego klasyfikatora, przeliczone
     * na procenty. Dzięki temu wszystkie wymiary są w tej samej skali i dają się porównywać
     * między utworami.
     */
    public function testAudioFeaturesAreProbabilitiesOfCanonicalClasses(): void
    {
        $result = $this->createClassifierResult([
            'danceability' => [ 'all' => [ 'danceable' => 0.812, 'not_danceable' => 0.188 ] ],
            'mood_happy' => [ 'all' => [ 'happy' => 0.25, 'not_happy' => 0.75 ] ],
            'timbre' => [ 'all' => [ 'bright' => 0.4, 'dark' => 0.6 ] ],
            'voice_instrumental' => [ 'all' => [ 'instrumental' => 0.9, 'voice' => 0.1 ] ],
        ]);

        self::assertSame(
            [ 'danceability' => 81, 'mood_happy' => 25, 'timbre' => 40, 'voice_instrumental' => 10 ],
            $result->getAudioFeatures(),
        );
    }

    /** Starszy wynik klasyfikacji może nie zawierać części klasyfikatorów */
    public function testMissingClassifiersAreSkipped(): void
    {
        $result = $this->createClassifierResult([
            'timbre' => [ 'all' => [ 'bright' => 0.55, 'dark' => 0.45 ] ],
        ]);

        self::assertSame([ 'timbre' => 55 ], $result->getAudioFeatures());
    }

    public function testResultWithoutHighLevelSectionHasNoAudioFeatures(): void
    {
        self::assertSame([], $this->createClassifierResult(null)->getAudioFeatures());
    }

    /**
     * Klasyfikatory świadomie pominięte: genre_electronic powtarza sygnał gatunku, a moods_mirex
     * jest skorelowany z pozostałymi wymiarami nastroju.
     */
    public function testDeliberatelyExcludedClassifiersAreNotPartOfVector(): void
    {
        $result = $this->createClassifierResult([
            'genre_electronic' => [ 'all' => [ 'house' => 0.7, 'techno' => 0.3 ] ],
            'moods_mirex' => [ 'all' => [ 'Cluster1' => 0.6, 'Cluster2' => 0.4 ] ],
            'timbre' => [ 'all' => [ 'bright' => 0.5, 'dark' => 0.5 ] ],
        ]);

        self::assertSame([ 'timbre' => 50 ], $result->getAudioFeatures());
    }

    /**
     * Wektor cech jest projekcją sekcji `highlevel` surowego wyniku, dlatego test buduje obiekt
     * wprost z tej sekcji. Przejście przez fromResultFile() wymagałoby kompletnego wyniku
     * klasyfikacji, w tym klasyfikatorów, które do wektora nie wchodzą.
     *
     * @link https://essentia.upf.edu/streaming_extractor_music.html
     */
    private function createClassifierResult(?array $highLevel): MusicClassifierResult
    {
        $rawResult = $highLevel !== null ? [ 'highlevel' => $highLevel ] : [];

        $reflection = new ReflectionClass(MusicClassifierResult::class);

        /** @var MusicClassifierResult $result */
        $result = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('rawResult')->setValue($result, $rawResult);

        return $result;
    }
}
