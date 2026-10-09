<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Collection\Extension\MusicalKeyInfo;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Favorite;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\IndexedPeriod;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Publisher;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\TrackDto;
use Psr\Http\Message\ServerRequestInterface;

final class SimilarityParametersForm
{
    /** Used in the template */
    public const string NAME_PROVIDERS = 'providers';

    /** Used in the template */
    public const string NAME_MODE = 'mode';

    public function __construct(
        /** @var SimilarityParameter[] */
        public readonly array $similarityParameters,
        public readonly SimilarityParametersRequest $request,
    ) {
    }

    public static function create(TrackDto $trackDto, ServerRequestInterface $request): self
    {
        $queryParams = $request->getQueryParams();

        $trackBpm = $trackDto->getBpm();
        $trackGenre = $trackDto->getGenre();
        $trackKey = $trackDto->getInitialKey();
        $trackYear = $trackDto->getYear();

        $request = new SimilarityParametersRequest(
            self::getMode($queryParams),
            self::getProviders($queryParams) ?? Similarity::DEFAULT_PROVIDERS,
            $queryParams[Bpm::NAME] ?? $trackBpm,
            $queryParams[Genre::NAME] ?? $trackGenre,
            $queryParams[MusicalKey::NAME] ?? $trackKey,
            $queryParams[Year::NAME] ?? $trackYear,
        );

        $trackMaxYear = (new \DateTime())->format('Y');

        $parameters = [
            new SimilarityParameter(Musly::NAME, 'Musly'),
            new SimilarityParameter(Publisher::NAME, 'Wytwórnia'),
            new SimilarityParameter(IndexedPeriod::NAME, 'Okres'),
            new SimilarityParameter(Favorite::NAME, 'Ulubione'),
            new SimilarityParameter(Genre::NAME, 'Gatunek', 'text', $request->genre, $trackGenre),
            new SimilarityParameter(Year::NAME, 'Rok', 'number', $request->year, $trackYear, 1980, $trackMaxYear),
            new SimilarityParameter(Bpm::NAME, 'BPM', 'number', $request->bpm, $trackBpm, 50, 200, 0.1),
            new SimilarityParameter(
                MusicalKey::NAME,
                'Tonacja',
                'text',
                $request->musicalKey,
                $trackKey,
                inputSuggestions: self::getMusicalKeySuggestions($trackKey),
            ),
        ];

        return new self($parameters, $request);
    }

    /** @noinspection PhpUnused, Used in the template */
    public function isProviderEnabled(string $providerName): bool
    {
        return in_array($providerName, $this->request->providers);
    }

    /**
     * Parametr pochodzi z adresu, więc nieznany preset, tak jak nieznany dostawca,
     * oznacza „Podobne”, a nie błąd 500
     */
    private static function getMode(array $queryParams): RelatedTracksMode
    {
        $mode = $queryParams[self::NAME_MODE] ?? null;

        if (!is_string($mode)) {
            return RelatedTracksMode::SIMILAR;
        }

        return RelatedTracksMode::tryFrom($mode) ?? RelatedTracksMode::SIMILAR;
    }

    /**
     * AI: relacje harmoniczne tonacji utworu jako podpowiedzi pola „Tonacja”
     *
     * @return array<string, string>
     */
    private static function getMusicalKeySuggestions(?string $trackKey): array
    {
        $musicalKeyInfo = $trackKey ? MusicalKeyInfo::factory()->get($trackKey) : null;

        if (!$musicalKeyInfo) {
            return [];
        }

        return array_column($musicalKeyInfo['similarKeys'], 'title', 'value');
    }

    /**
     * Parametry pochodzą z adresu, więc nazwy nieznanych dostawców są pomijane, a puste żądanie
     * oznacza zestaw domyślny. Bez tego nieznana nazwa kończy się wyjątkiem i błędem 500.
     */
    private static function getProviders(array $queryParams): ?array
    {
        $providers = $queryParams[self::NAME_PROVIDERS] ?? null;

        if (!$providers) {
            return null;
        }

        return $providers
            |> (fn ($providers) => is_array($providers) ? $providers : [$providers])
            |> (fn ($providers) => array_filter($providers, 'is_string'))
            |> (fn ($providers) => array_intersect($providers, Similarity::PROVIDERS))
            |> array_values(...)
            ?: null;
    }
}
