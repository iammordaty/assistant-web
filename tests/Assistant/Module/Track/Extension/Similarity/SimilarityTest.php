<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

require_once __DIR__ . '/DeprecationGuard.php';
require_once __DIR__ . '/FixedValueProvider.php';
require_once __DIR__ . '/TrackFactory.php';

final class SimilarityTest extends TestCase
{
    use DeprecationGuard;

    /** Wagi zgodne z config/config.inc */
    private const array PROVIDERS_WEIGHTS = [
        Bpm::NAME => 0.70,
        Genre::NAME => 0.75,
        MusicalKey::NAME => 0.90,
        Musly::NAME => 1,
        Year::NAME => 0.60,
    ];

    private const array PROVIDER_DOUBLES = [
        Bpm::NAME => FixedValueBpmProvider::class,
        Genre::NAME => FixedValueGenreProvider::class,
        MusicalKey::NAME => FixedValueMusicalKeyProvider::class,
        Musly::NAME => FixedValueMuslyProvider::class,
        Year::NAME => FixedValueYearProvider::class,
    ];

    /**
     * Maksymalna wartość każdego dostawcy musi dawać dokładnie 100 dla dowolnego zestawu dostawców.
     * Wcześniej mianownik normalizacji był obcinany do liczby całkowitej, więc większość zestawów
     * (np. Musly + Genre) zwracała 101.
     *
     * @dataProvider providerSubsets
     */
    public function testMaximumProviderValuesGiveHundredForEverySubset(array $providerNames): void
    {
        $similarity = $this->createSimilarity($providerNames, 100);

        self::assertSame(100, $similarity->getSimilarityValue(TrackFactory::create(), TrackFactory::create()));
    }

    /** @dataProvider providerSubsets */
    public function testZeroProviderValuesGiveZeroForEverySubset(array $providerNames): void
    {
        $similarity = $this->createSimilarity($providerNames, 0);

        self::assertSame(0, $similarity->getSimilarityValue(TrackFactory::create(), TrackFactory::create()));
    }

    /** @dataProvider providerSubsets */
    public function testHalfProviderValuesGiveHalfForEverySubset(array $providerNames): void
    {
        $similarity = $this->createSimilarity($providerNames, 50);

        self::assertSame(50, $similarity->getSimilarityValue(TrackFactory::create(), TrackFactory::create()));
    }

    /** @dataProvider weightedMeanCases */
    public function testSimilarityValueIsWeightedMean(
        array $providerValues,
        array $providersWeights,
        int $expectedSimilarityValue,
    ): void {
        $providers = [];

        foreach ($providerValues as $providerName => $providerValue) {
            $providers[] = new (self::PROVIDER_DOUBLES[$providerName])($providerValue);
        }

        $similarity = new ReflectionClass(Similarity::class);
        $service = $this->buildSimilarity($similarity, $providers, $providersWeights);

        self::assertSame(
            $expectedSimilarityValue,
            $service->getSimilarityValue(TrackFactory::create(), TrackFactory::create()),
        );
    }

    public function testProviderSimilarityValuesAreKeyedByProviderName(): void
    {
        $providers = [
            new FixedValueBpmProvider(70),
            new FixedValueMuslyProvider(40),
        ];

        $service = $this->buildSimilarity(new ReflectionClass(Similarity::class), $providers, self::PROVIDERS_WEIGHTS);

        self::assertSame(
            [ Bpm::NAME => 70, Musly::NAME => 40 ],
            $service->getProviderSimilarityValues(TrackFactory::create(), TrackFactory::create()),
        );
    }

    public function testSetupRejectsProviderWithoutWeight(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Weight not defined for provider "Musly"');

        $this->createSimilarity([ Musly::NAME ], 100, [ Bpm::NAME => 0.70 ]);
    }

    public function testSetupRejectsDuplicatedProviderName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has duplicate name');

        $providers = [ new FixedValueMuslyProvider(100), new FixedValueMuslyProvider(100) ];

        $this->buildSimilarity(new ReflectionClass(Similarity::class), $providers, self::PROVIDERS_WEIGHTS);
    }

    /** Wszystkie 31 niepustych podzbiorów pięciu dostawców */
    public static function providerSubsets(): iterable
    {
        $providerNames = Similarity::PROVIDERS;
        $providersCount = count($providerNames);

        for ($mask = 1; $mask < 2 ** $providersCount; $mask++) {
            $subset = [];

            for ($index = 0; $index < $providersCount; $index++) {
                if ($mask & (1 << $index)) {
                    $subset[] = $providerNames[$index];
                }
            }

            yield implode(' + ', $subset) => [ $subset ];
        }
    }

    public static function weightedMeanCases(): iterable
    {
        // 0,7*70 + 0,75*55 + 0,9*95 + 1*40 + 0,6*20 = 227,75 przy maksimum 395
        yield 'wszyscy dostawcy' => [
            [ Bpm::NAME => 70, Genre::NAME => 55, MusicalKey::NAME => 95, Musly::NAME => 40, Year::NAME => 20 ],
            self::PROVIDERS_WEIGHTS,
            58,
        ];

        // 1*100 + 0,75*0 = 100 przy maksimum 175
        yield 'Musly bez gatunku' => [
            [ Musly::NAME => 100, Genre::NAME => 0 ],
            self::PROVIDERS_WEIGHTS,
            57,
        ];

        // 0,7*98 + 0,75*90 + 0,9*65 = 194,6 przy maksimum 235
        yield 'bez Musly i roku' => [
            [ Bpm::NAME => 98, Genre::NAME => 90, MusicalKey::NAME => 65 ],
            self::PROVIDERS_WEIGHTS,
            83,
        ];

        // wagi niecałkowite w sumie: 0,33*100 + 0,67*0 = 33 przy maksimum 100
        yield 'wagi o sumie jednostkowej' => [
            [ Bpm::NAME => 100, Genre::NAME => 0 ],
            [ Bpm::NAME => 0.33, Genre::NAME => 0.67 ],
            33,
        ];
    }

    private function createSimilarity(array $providerNames, int $providerValue, ?array $providersWeights = null): Similarity
    {
        $providers = array_map(
            fn (string $providerName) => new (self::PROVIDER_DOUBLES[$providerName])($providerValue),
            $providerNames,
        );

        return $this->buildSimilarity(
            new ReflectionClass(Similarity::class),
            $providers,
            $providersWeights ?? self::PROVIDERS_WEIGHTS,
        );
    }

    /**
     * Similarity wymaga w konstruktorze finalnej klasy TrackSearchService, której punktacja nie używa
     * i której nie da się zastąpić atrapą, dlatego instancja powstaje bez konstruktora,
     * z ręcznym wywołaniem setup().
     *
     * @param ProviderInterface[] $providers
     */
    private function buildSimilarity(ReflectionClass $reflection, array $providers, array $providersWeights): Similarity
    {
        /** @var Similarity $service */
        $service = $reflection->newInstanceWithoutConstructor();

        $properties = [
            'providers' => $providers,
            'providersWeights' => $providersWeights,
            'minSimilarityValue' => 60,
            'maxTracks' => 200,
        ];

        foreach ($properties as $name => $value) {
            $reflection->getProperty($name)->setValue($service, $value);
        }

        $reflection->getMethod('setup')->invoke($service);

        return $service;
    }
}
