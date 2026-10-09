<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Favorite;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\IndexedPeriod;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Extension\Similarity\Provider\Publisher;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Model\Track;
use KeyTools\KeyTools;

final class SimilarityBuilder
{
    /**
     * AI: granica tempa dla trybu „Zmiana pulsu”: wolniejsze utwory są podwajane (double-time),
     * szybsze dzielone na pół (half-time). Jedno kryterium BPM nie pozwala szukać w obu kierunkach naraz.
     */
    private const float PULSE_SWITCH_BPM = 110;

    private array $providers = Similarity::DEFAULT_PROVIDERS;

    private bool $muslyCandidates = true;

    private Similarity $similarityService;

    private ?Track $track = null;
    private ?SimilarityParametersForm $similarityParametersForm = null;

    public function __construct(
        private TrackSearchService $trackSearchService,
        private SimilarTracksCollectionService $service,
        private array $providersParameters,
        private array $providersWeights,
        private int $minSimilarityValue,
        private int $maxTracks,
        private array $modesParameters,
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

    /**
     * AI: formularz jest jedynym źródłem dostawców i celu wyszukiwania (preset wpisał do niego przesunięte
     * tempo i tonację), a preset dostarcza wyłącznie swoje wagi i tolerancje
     */
    public function withForm(SimilarityParametersForm $similarityParametersForm): self
    {
        $request = $similarityParametersForm->request;

        $this->withMode($request->mode);

        if ($request->providers) {
            $this->setProviders($request->providers);
        }

        $this->similarityParametersForm = $similarityParametersForm;

        return $this;
    }

    public function withMode(RelatedTracksMode $mode): self
    {
        $modeParameters = $this->modesParameters[$mode->value] ?? null;

        if (!$modeParameters) {
            return $this;
        }

        $this->setProviders(array_keys($modeParameters['providers_weights']));

        // wagi presetu nadpisują podstawowe, a dostawca dołączony ręcznie zachowuje wagę podstawową
        $this->providersWeights = array_replace($this->providersWeights, $modeParameters['providers_weights']);
        $this->providersParameters = array_replace_recursive(
            $this->providersParameters,
            $modeParameters['providers_parameters'] ?? [],
        );

        $this->muslyCandidates = $modeParameters['musly_candidates'] ?? true;

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

        if ($this->isProviderEnabled(Favorite::NAME)) {
            $providers[] = new Favorite();
        }

        if ($this->isProviderEnabled(Genre::NAME)) {
            $providers[] = new Genre($this->providersParameters[Genre::NAME]);
        }

        if ($this->isProviderEnabled(IndexedPeriod::NAME)) {
            $providers[] = new IndexedPeriod($this->providersParameters[IndexedPeriod::NAME]);
        }

        if ($this->isProviderEnabled(MusicalKey::NAME)) {
            $providers[] = new MusicalKey();
        }

        if ($this->isProviderEnabled(Musly::NAME)) {
            $providers[] = new Musly($this->service, $this->muslyCandidates);
        }

        if ($this->isProviderEnabled(Publisher::NAME)) {
            $providers[] = new Publisher();
        }

        if ($this->isProviderEnabled(Year::NAME)) {
            $providers[] = new Year($this->providersParameters[Year::NAME]);
        }

        $this->similarityService = new Similarity(
            new SimilarityCandidatesFinder($this->trackSearchService, $providers),
            new SimilarityCalculator($providers, $this->providersWeights),
            new SimilarityCandidatesSorter(),
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

    /**
     * Zwraca presety z celem wyliczonym dla podanego utworu, w kolejności przycisków
     *
     * @return RelatedTracksPreset[]
     */
    public function getPresets(Track $track): array
    {
        $presets = [];

        foreach (RelatedTracksMode::cases() as $mode) {
            $modeParameters = $this->modesParameters[$mode->value] ?? null;

            $providers = $modeParameters
                ? array_keys($modeParameters['providers_weights'])
                : Similarity::DEFAULT_PROVIDERS;

            $targetTrack = $this->applyTrackOffsets($track, $modeParameters['track_offsets'] ?? []);

            $presets[] = new RelatedTracksPreset($mode, $providers, [
                Bpm::NAME => $targetTrack->getBpm(),
                Genre::NAME => $targetTrack->getGenre(),
                MusicalKey::NAME => $targetTrack->getInitialKey(),
                Year::NAME => $targetTrack->getYear(),
            ]);
        }

        return $presets;
    }

    /** Przesuwa cel wyszukiwania zgodnie z presetem */
    private function applyTrackOffsets(Track $track, array $trackOffsets): Track
    {
        $bpmPercent = $trackOffsets['bpm_percent'] ?? 0;
        $bpmPulse = $trackOffsets['bpm_pulse'] ?? false;
        $keyStep = $trackOffsets['musical_key'] ?? 0;
        $keyScale = $trackOffsets['musical_key_scale'] ?? null;

        // tempo przesuwane procentowo brzmi tak samo niezależnie od tempa bazowego (90, 128, 174 BPM)
        if ($bpmPercent && $track->getBpm()) {
            $track = $track->withBpm(round($track->getBpm() * (1 + $bpmPercent / 100), 1));
        }

        if ($bpmPulse && $track->getBpm()) {
            $bpm = $track->getBpm();

            $track = $track->withBpm($bpm < self::PULSE_SWITCH_BPM ? $bpm * 2 : $bpm / 2);
        }

        if (!$keyStep && !$keyScale) {
            return $track;
        }

        $keyTools = KeyTools::fromNotation(KeyTools::NOTATION_CAMELOT_KEY);
        $initialKey = $track->getInitialKey();

        // tonacja może pochodzić z formularza, więc nie musi być poprawna
        if (!$initialKey || !$keyTools->isValidKey($initialKey)) {
            return $track;
        }

        // 'toggle' zawsze zmienia skalę; 'A' lub 'B' wskazuje skalę docelową (przejścia diagonalne, np. 8A → 9B)
        $toggleScale = $keyScale === 'toggle' || ($keyScale && !str_ends_with($initialKey, $keyScale));

        return $track->withInitialKey($keyTools->calculateKey($initialKey, $keyStep, $toggleScale));
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
