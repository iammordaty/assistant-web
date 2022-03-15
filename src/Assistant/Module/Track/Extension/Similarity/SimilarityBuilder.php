<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;

final class SimilarityBuilder
{
    private array $providers = Similarity::PROVIDERS;
    private ?Track $track = null;
    private Similarity $similarityService;

    private ?SimilarityParametersForm $similarityParametersForm;

    public function __construct(
        private TrackSearchService $trackSearchService,
        private SimilarTracksCollectionService $service,
        private array $providersParameters,
        private array $providersWeights,
        private int $minSimilarityValue,
        private int $maxTracks,
    ) {
    }

    public function withTrack(Track $track): self
    {
        $this->track = $track;

        return $this;
    }

    /**
     * @param string[] $providers
     * @return self
     */
    public function withProviders(array $providers): self
    {
        $this->setProviders($providers);

        return $this;
    }

    public function withProviderParameters(array $parameters): self
    {
        $this->providersParameters = $parameters;

        return $this;
    }

    public function withForm(SimilarityParametersForm $similarityParametersForm): self
    {
        if ($similarityParametersForm->request->providers) {
            $this->setProviders($similarityParametersForm->request->providers);
        }

        $this->similarityParametersForm = $similarityParametersForm;

        return $this;
    }

    public function withProviderWeights(array $weights): self
    {
        $this->providersWeights = $weights;

        return $this;
    }

    public function withMinSimilarityValue(int $minSimilarityValue): self
    {
        $this->minSimilarityValue = $minSimilarityValue;

        return $this;
    }

    public function withMaxTracks(int $maxTracks): self
    {
        $this->maxTracks = $maxTracks;

        return $this;
    }

    public function createService(): self
    {
        /** @var ProviderInterface[] $providers */
        $providers = [];

        if ($this->isProviderEnabled(Bpm::NAME)) {
            $providers[] = new Bpm($this->providersParameters[Bpm::NAME]);
        }

        if ($this->isProviderEnabled(Genre::NAME)) {
            $providers[] = new Genre();
        }

        if ($this->isProviderEnabled(MusicalKey::NAME)) {
            $providers[] = new MusicalKey();
        }

        if ($this->isProviderEnabled(Musly::NAME)) {
            $providers[] = new Musly($this->service);
        }

        if ($this->isProviderEnabled(Year::NAME)) {
            $providers[] = new Year($this->providersParameters[Year::NAME]);
        }

        $this->similarityService = new Similarity(
            $this->trackSearchService,
            $providers,
            $this->providersWeights,
            $this->minSimilarityValue,
            $this->maxTracks,
        );

        return $this;
    }

    public function getSimilarityService(): Similarity
    {
        return $this->similarityService;
    }

    public function getSimilarTracks(?Track $track = null): array
    {
        $track = $track ?: $this->track;

        if (!$track) {
            throw new \RuntimeException(sprintf('Track must be set before calling the "%s" method', __METHOD__));
        }

        if ($this->similarityParametersForm) {
            $request = $this->similarityParametersForm->request;

            if ($request->year) {
                $track = $track->withYear($request->year);
            }

            if ($request->genre) {
                $track = $track->withGenre($request->genre);
            }

            if ($request->bpm) {
                $track = $track->withBpm($request->bpm);
            }

            if ($request->musicalKey) {
                $track = $track->withInitialKey($request->musicalKey);
            }
        }

        return $this->similarityService->getSimilarTracks($track);
    }

    private function isProviderEnabled(string $name): bool
    {
        return in_array($name, $this->providers);
    }

    private function setProviders(array $providers): void
    {
        foreach ($providers as $providerName) {
            if (!in_array($providerName, Similarity::PROVIDERS)) {
                throw new \RuntimeException(sprintf('Unknown provider: "%s"', $providerName));
            }
        }

        $this->providers = $providers;
    }
}
