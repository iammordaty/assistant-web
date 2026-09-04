<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\ProviderInterface;
use Assistant\Module\Track\Model\Track;

/** Oblicza podobieństwo pomiędzy parą utworów na podstawie wskazań dostawców */
final class SimilarityCalculator
{
    /**
     * @param ProviderInterface[] $providers
     * @param array $providersWeights
     */
    public function __construct(
        private array $providers,
        private array $providersWeights,
    ) {
        if (count($this->providers) === 0) {
            throw new \RuntimeException('At least one similarity provider must be enabled');
        }

        $this->setup();
    }

    /**
     * Oblicza podobieństwo pomiędzy utworami
     *
     * Mianownik liczony jest dla każdej pary osobno, wyłącznie z dostawców, którzy mieli dane.
     * Dzięki temu utwór z niepełnymi tagami nie jest karany za brak danych, a wynik pozostaje
     * w skali 0-100 niezależnie od tego, ilu dostawców jest włączonych.
     */
    public function calculate(Track $baseTrack, Track $comparedTrack): int
    {
        $weightedSimilarity = 0.0;
        $maxWeightedSimilarity = 0.0;

        foreach ($this->providers as $provider) {
            $providerSimilarity = $provider->getSimilarityValue($baseTrack, $comparedTrack);

            if ($providerSimilarity === null) {
                continue;
            }

            $maxProviderSimilarity = $provider->getMaxSimilarityValue();
            $providerWeight = $this->providersWeights[$provider::NAME];

            $weightedSimilarity += $providerSimilarity * $providerWeight;
            $maxWeightedSimilarity += $maxProviderSimilarity * $providerWeight;
        }

        if ($maxWeightedSimilarity <= 0.0) {
            return 0;
        }

        return (int) round($weightedSimilarity * 100 / $maxWeightedSimilarity);
    }

    /** Sprawdza poprawność konfiguracji dostawców */
    private function setup(): void
    {
        $providerNames = [];

        foreach ($this->providers as $provider) {
            $providerName = $provider->getName();

            if (!$providerName) {
                $message = sprintf('Provider class "%s" has invalid name (name can not be empty)', $provider::class);

                throw new \RuntimeException($message);
            }

            if (in_array($providerName, $providerNames)) {
                $message = sprintf('Provider class "%s" has duplicate name "%s"', $provider::class, $providerName);

                throw new \RuntimeException($message);
            }

            $providerNames[] = $providerName;

            if (!isset($this->providersWeights[$providerName])) {
                $message = sprintf('Weight not defined for provider "%s"', $providerName);

                throw new \RuntimeException($message);
            }

            unset($providerName, $provider);
        }
    }
}
