<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\TrackDto;
use Psr\Http\Message\ServerRequestInterface;

final class SimilarityParametersForm
{
    /** Used in the template */
    public const NAME_PROVIDERS = 'providers';

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
            $queryParams[self::NAME_PROVIDERS] ?? Similarity::PROVIDERS,
            $queryParams[Bpm::NAME] ?? $trackBpm,
            $queryParams[Genre::NAME] ?? $trackGenre,
            $queryParams[MusicalKey::NAME] ?? $trackKey,
            $queryParams[Year::NAME] ?? $trackYear,
        );

        $trackMaxYear = (new \DateTime())->format('Y');

        $parameters = [
            new SimilarityParameter(Musly::NAME, 'Musly'),
            new SimilarityParameter(Genre::NAME, 'Gatunek', 'text', $request->genre, $trackGenre),
            new SimilarityParameter(Year::NAME, 'Rok', 'number', $request->year, $trackYear, 1980, $trackMaxYear),
            new SimilarityParameter(Bpm::NAME, 'BPM', 'number', $request->bpm, $trackBpm, 50, 200, 0.1),
            new SimilarityParameter(MusicalKey::NAME, 'Tonacja', 'text', $request->musicalKey, $trackKey),
        ];

        return new self($parameters, $request);
    }

    /** @noinspection PhpUnused, Used in the template */
    public function isProviderEnabled(string $providerName): bool
    {
        return in_array($providerName, $this->request->providers);
    }
}
