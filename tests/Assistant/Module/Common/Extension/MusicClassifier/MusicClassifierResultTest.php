<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use PHPUnit\Framework\TestCase;

final class MusicClassifierResultTest extends TestCase
{
    /**
     * Odpowiedź API dla „Gorgon City & Interplanetary Criminal - Contact [Extended]”
     * (rescan-log-singles-2026.txt), skrócona do cech istotnych dla hashtagów.
     */
    public function testHashtagsContainOnlyConfidentFeaturesByConfidence(): void
    {
        $result = MusicClassifierResult::fromResponse($this->contactResponse());

        self::assertSame(
            [
                '#speedgarage' => 'genre',
                '#energetic' => 'mood',
                '#sampler' => 'instrument',
            ],
            array_map(static fn (array $feature): string => $feature['type'], $result->getHashtags()),
        );
    }

    public function testHashtagKeepsConfidence(): void
    {
        $result = MusicClassifierResult::fromResponse($this->contactResponse());

        self::assertSame(0.3281, $result->getHashtags()['#speedgarage']['confidence']);
    }

    /** Gatunek „Techno” i tag „techno” to ta sama cecha, więc zostaje tylko pewniejsze wystąpienie */
    public function testGenreAndTagWithTheSameLabelAreMerged(): void
    {
        $result = MusicClassifierResult::fromResponse($this->response(
            genres: [ [ 'genre' => 'Techno', 'confidence' => 0.4 ] ],
            tags: [ [ 'label' => 'techno', 'type' => 'tag', 'confidence' => 0.6 ] ],
        ));

        self::assertSame([ '#techno' => [ 'type' => 'tag', 'confidence' => 0.6 ] ], $result->getHashtags());
    }

    /** Pomijane hashtagi nie zajmują miejsca w limicie */
    public function testSkippedHashtagsAreNotCountedInLimit(): void
    {
        $tags = array_map(
            static fn (int $number): array => [ 'label' => 'tag ' . $number, 'type' => 'tag', 'confidence' => 0.8 ],
            range(1, 8),
        );

        $result = MusicClassifierResult::fromResponse($this->response(
            tags: [ [ 'label' => 'synthesizer', 'type' => 'instrument', 'confidence' => 0.9 ], ...$tags ],
        ));

        self::assertSame(
            [ '#tag1', '#tag2', '#tag3', '#tag4', '#tag5', '#tag6', '#tag7', '#tag8' ],
            array_keys($result->getHashtags()),
        );
    }

    public function testHashtagsAreLimited(): void
    {
        $tags = array_map(
            static fn (int $number): array => [ 'label' => 'tag ' . $number, 'type' => 'tag', 'confidence' => 0.9 ],
            range(1, 10),
        );

        $result = MusicClassifierResult::fromResponse($this->response(tags: $tags));

        self::assertCount(8, $result->getHashtags());
    }

    public function testNoHashtagsWhenNothingIsConfident(): void
    {
        $result = MusicClassifierResult::fromResponse($this->response(
            genres: [ [ 'genre' => 'Techno', 'confidence' => 0.1 ] ],
            tags: [ [ 'label' => 'piano', 'type' => 'instrument', 'confidence' => 0.29 ] ],
        ));

        self::assertSame([], $result->getHashtags());
    }

    private function contactResponse(): array
    {
        return $this->response(
            genres: [
                [ 'genre' => 'Speed Garage', 'confidence' => 0.3281 ],
                [ 'genre' => 'Trance', 'confidence' => 0.2022 ],
                [ 'genre' => 'House', 'confidence' => 0.198 ],
                [ 'genre' => 'Tech Trance', 'confidence' => 0.1371 ],
                [ 'genre' => 'UK Garage', 'confidence' => 0.1097 ],
            ],
            tags: [
                [ 'label' => 'synthesizer', 'type' => 'instrument', 'confidence' => 0.8377 ],
                [ 'label' => 'electronic', 'type' => 'tag', 'confidence' => 0.8038 ],
                [ 'label' => 'dance', 'type' => 'tag', 'confidence' => 0.6079 ],
                [ 'label' => 'bass', 'type' => 'instrument', 'confidence' => 0.4374 ],
                [ 'label' => 'energetic', 'type' => 'mood', 'confidence' => 0.3099 ],
                [ 'label' => 'sampler', 'type' => 'instrument', 'confidence' => 0.3051 ],
                [ 'label' => 'house', 'type' => 'tag', 'confidence' => 0.2994 ],
                [ 'label' => 'drummachine', 'type' => 'instrument', 'confidence' => 0.2309 ],
            ],
        );
    }

    private function response(array $genres = [], array $tags = []): array
    {
        return [
            'audio_md5' => '0ea476e3977df9c064003ab22927828e',
            'bpm' => [ 'value' => 136.0, 'confidence' => 2.7273 ],
            'key' => [ 'value' => 'F# minor', 'confidence' => 0.6188 ],
            'genre' => $genres,
            'tags' => $tags,
        ];
    }
}
