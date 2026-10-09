<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Model\Track;
use PHPUnit\Framework\TestCase;

// AI: testy nie mają własnego autoloadera, a fabryka utworu jest współdzielona przez kilka testów modułu
require_once __DIR__ . '/SimilarityTestTrack.php';

final class SimilarityBuilderTest extends TestCase
{
    /** @dataProvider dataTrackOffsets */
    public function testTrackOffsetsShiftSearchTarget(array $trackOffsets, Track $track, ?float $expectedBpm, ?string $expectedKey): void
    {
        $shiftedTrack = $this->applyTrackOffsets($trackOffsets, $track);

        self::assertSame($expectedBpm, $shiftedTrack->getBpm());
        self::assertSame($expectedKey, $shiftedTrack->getInitialKey());
    }

    public function dataTrackOffsets(): array
    {
        return [
            'brak przesunięcia' => [ [], SimilarityTestTrack::create(), 128.0, '8A' ],
            'tempo w procentach' => [ [ 'bpm_percent' => 2.5 ], SimilarityTestTrack::create(), 131.2, '8A' ],
            'tempo w dół' => [ [ 'bpm_percent' => -2.5 ], SimilarityTestTrack::create(bpm: 174.0), 169.7, '8A' ],
            'double-time' => [ [ 'bpm_pulse' => true ], SimilarityTestTrack::create(bpm: 87.0), 174.0, '8A' ],
            'half-time' => [ [ 'bpm_pulse' => true ], SimilarityTestTrack::create(bpm: 140.0), 70.0, '8A' ],
            'kwinta w górę przez granicę koła' => [ [ 'musical_key' => 1 ], SimilarityTestTrack::create(initialKey: '12A'), 128.0, '1A' ],
            'kwarta w górę przez granicę koła' => [ [ 'musical_key' => -1 ], SimilarityTestTrack::create(initialKey: '1A'), 128.0, '12A' ],
            'cały ton w górę' => [ [ 'musical_key' => 2 ], SimilarityTestTrack::create(initialKey: '8B'), 128.0, '10B' ],
            'diagonalnie do dur' => [ [ 'musical_key' => 1, 'musical_key_scale' => 'B' ], SimilarityTestTrack::create(initialKey: '8A'), 128.0, '9B' ],
            'docelowa skala już osiągnięta' => [ [ 'musical_key' => 1, 'musical_key_scale' => 'B' ], SimilarityTestTrack::create(initialKey: '8B'), 128.0, '9B' ],
            'diagonalnie do moll' => [ [ 'musical_key' => -1, 'musical_key_scale' => 'A' ], SimilarityTestTrack::create(initialKey: '8B'), 128.0, '7A' ],
            'tonacja równoległa' => [ [ 'musical_key_scale' => 'toggle' ], SimilarityTestTrack::create(initialKey: '8A'), 128.0, '8B' ],
            'niepoprawna tonacja' => [ [ 'musical_key' => 1 ], SimilarityTestTrack::create(initialKey: 'Xm'), 128.0, 'Xm' ],
            'brak tempa i tonacji' => [ [ 'bpm_percent' => 2.5, 'musical_key' => 1 ], SimilarityTestTrack::create(bpm: null, initialKey: null), null, null ],
        ];
    }

    public function testModeSetsProvidersAndMergesParameters(): void
    {
        $builder = $this->createBuilder([
            RelatedTracksMode::LIFT->value => [
                'providers_weights' => [ Bpm::NAME => 0.90, MusicalKey::NAME => 0.90 ],
                'providers_parameters' => [ Bpm::NAME => [ 'tolerance' => 1 ] ],
            ],
        ]);

        $builder->withMode(RelatedTracksMode::LIFT);

        self::assertSame([ Bpm::NAME, MusicalKey::NAME ], $this->getProperty($builder, 'providers'));
        self::assertSame(1, $this->getProperty($builder, 'providersParameters')[Bpm::NAME]['tolerance']);
        self::assertSame(5, $this->getProperty($builder, 'providersParameters')['Year']['tolerance']);
    }

    public function testModeWithoutParametersKeepsDefaults(): void
    {
        $builder = $this->createBuilder([]);

        $builder->withMode(RelatedTracksMode::PLANNING);

        self::assertSame(Similarity::DEFAULT_PROVIDERS, $this->getProperty($builder, 'providers'));
    }

    private function applyTrackOffsets(array $trackOffsets, Track $track): Track
    {
        $builder = $this->createBuilder([]);

        (new \ReflectionProperty(SimilarityBuilder::class, 'trackOffsets'))->setValue($builder, $trackOffsets);

        return (new \ReflectionMethod(SimilarityBuilder::class, 'applyTrackOffsets'))->invoke($builder, $track);
    }

    /** Usługi wyszukiwania nie są potrzebne do konfiguracji buildera, więc konstruktor jest pomijany */
    private function createBuilder(array $modesParameters): SimilarityBuilder
    {
        $builder = (new \ReflectionClass(SimilarityBuilder::class))->newInstanceWithoutConstructor();

        (new \ReflectionProperty(SimilarityBuilder::class, 'modesParameters'))->setValue($builder, $modesParameters);
        (new \ReflectionProperty(SimilarityBuilder::class, 'providersParameters'))->setValue($builder, [
            Bpm::NAME => [ 'tolerance' => 5 ],
            'Year' => [ 'tolerance' => 5 ],
        ]);
        (new \ReflectionProperty(SimilarityBuilder::class, 'providersWeights'))->setValue($builder, []);

        return $builder;
    }

    private function getProperty(SimilarityBuilder $builder, string $name): mixed
    {
        return (new \ReflectionProperty(SimilarityBuilder::class, $name))->getValue($builder);
    }
}
