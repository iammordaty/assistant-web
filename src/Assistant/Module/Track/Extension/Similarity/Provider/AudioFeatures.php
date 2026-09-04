<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

/**
 * Provider podobieństwa oparty na wektorze cech wysokopoziomowych z klasyfikatora audio.
 *
 * Sygnał jest częściowo niezależny od Musly: tam podobieństwo wynika z modelu gaussowskiego MFCC,
 * tu z prawdopodobieństw klasyfikatorów opisujących taneczność, nastrój, barwę i obecność wokalu.
 * Wektor jest zapisywany w utworze przy indeksacji, więc porównanie nie wymaga dostępu do plików
 * z wynikami klasyfikacji.
 *
 * @see \Assistant\Module\Common\Extension\MusicClassifier\MusicClassifierResult::getAudioFeatures()
 */
final class AudioFeatures extends AbstractProvider
{
    /** {@inheritDoc} */
    public const string NAME = 'AudioFeatures';

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        $baseFeatures = $baseTrack->getAudioFeatures();
        $comparedFeatures = $comparedTrack->getAudioFeatures();

        // porównywane są tylko wymiary obecne po obu stronach, bo wynik klasyfikacji mógł powstać
        // przy innym zestawie klasyfikatorów
        $names = array_keys(array_intersect_key($baseFeatures, $comparedFeatures));

        if (!$names) {
            return null;
        }

        $distance = 0.0;

        foreach ($names as $name) {
            $distance += abs($baseFeatures[$name] - $comparedFeatures[$name]);
        }

        // średnia odległość na wymiar; wszystkie wymiary są w skali 0-100, więc wynik też
        return (int) round(self::MAX_SIMILARITY_VALUE - $distance / count($names));
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        // odległość wektorów nie ma sensownej reprezentacji w zapytaniu do bazy
        return null;
    }
}
