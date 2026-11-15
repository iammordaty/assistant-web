<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use PHPUnit\Framework\TestCase;

final class MusicClassifierResultTest extends TestCase
{
    /**
     * Odpowiedź API dla „Gorgon City & Interplanetary Criminal - Contact [Extended]”
     * (rescan-log-singles-2026.txt), skrócona do cech istotnych dla tagów.
     */
    public function testTagsContainOnlyConfidentTagsByConfidence(): void
    {
        $result = MusicClassifierResult::fromResponse($this->contactResponse());

        self::assertSame(
            [
                [ 'speed garage', 'genre' ],
                [ 'energetic', 'mood' ],
                [ 'sampler', 'instrument' ],
                [ 'drummachine', 'instrument' ],
                [ 'trance', 'genre' ],
                [ 'tech trance', 'genre' ],
                [ 'uk garage', 'genre' ],
            ],
            array_map(
                static fn (MusicClassifierTag $tag): array => [ $tag->getName(), $tag->getType() ],
                $result->getTags(),
            ),
        );
    }

    public function testTagKeepsConfidence(): void
    {
        $result = MusicClassifierResult::fromResponse($this->contactResponse());

        self::assertSame(0.3281, $result->getTags()[0]->getConfidence());
    }

    /** Gatunek „Techno” i tag „techno” to ta sama cecha, więc zostaje tylko pewniejsze wystąpienie */
    public function testGenreAndTagWithTheSameLabelAreMerged(): void
    {
        $result = MusicClassifierResult::fromResponse($this->response(
            genres: [ [ 'genre' => 'Techno', 'confidence' => 0.4 ] ],
            tags: [ [ 'label' => 'techno', 'type' => 'tag', 'confidence' => 0.6 ] ],
        ));

        self::assertEquals([ new MusicClassifierTag('techno', 'tag', 0.6) ], $result->getTags());
    }

    /** Tagi wykrywane w większości utworów kolekcji nie trafiają na stronę utworu */
    public function testSkippedTagsAreOmitted(): void
    {
        $result = MusicClassifierResult::fromResponse($this->response(
            tags: [
                [ 'label' => 'synthesizer', 'type' => 'instrument', 'confidence' => 0.9 ],
                [ 'label' => 'piano', 'type' => 'instrument', 'confidence' => 0.5 ],
            ],
        ));

        self::assertEquals([ new MusicClassifierTag('piano', 'instrument', 0.5) ], $result->getTags());
    }

    public function testNoTagsWhenNothingIsConfident(): void
    {
        $result = MusicClassifierResult::fromResponse($this->response(
            genres: [ [ 'genre' => 'Techno', 'confidence' => 0.05 ] ],
            tags: [ [ 'label' => 'piano', 'type' => 'instrument', 'confidence' => 0.09 ] ],
        ));

        self::assertSame([], $result->getTags());
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
