<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Mix\Extension\Strategy\MostSimilarTrackStrategy;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\SimilarTracksVO;
use Assistant\Module\Track\Model\Track;

final class MixService
{
    public function __construct(
        private Similarity $similarity,
        private TrackSearchService $searchService,
    ) {
    }

    /**
     * Wrzucone na yolo, nie przywiązywać się
     *
     * @param string[] $listing
     * @return array
     */
    public function getMixInfo(array $listing): array
    {
        $tracks = $this->getTracks($listing); // być może to powinno być wyżej

        $strategy = new MostSimilarTrackStrategy($this->similarity);

        // @todo: dodać strategię, która dobierze najlepszy pierwszy kawałek dla MostSimilarTrackStrategy
        // @todo: dodać strategię, która dobierze najbardziej podobny następny kawałek (także do kolejnego),
        //        jeśli najlepiej różnica do następnego będzie większa od zadanej

        $arrangedMix = new Mix($strategy, $tracks);

        $mix = $arrangedMix->getMix();
        $similarityGrid = $arrangedMix->getSimilarityGrid();

        return [ $mix, $similarityGrid ];
    }

    /**
     * @param string[] $listing
     * @return Track[]
     */
    public function getTracks(array $listing): array
    {
        $tracks = [];

        $previousTrack = null;

        // @todo rozbić na pobranie, filter, mapowanie
        /** @see SimilarTracksVO */

        foreach ($listing as $trackName) {
            $track = $this->searchService->findOneByName($trackName);

            if (!$track) {
                // @todo: brak wyszukanego utworu powinien być komunikowany na froncie
                continue;
            }

            $similarityValue = $previousTrack ? $this->similarity->getSimilarityValue($previousTrack, $track) : null;

            $tracks[] = [
                'track' => $track,
                'similarityValue' => $similarityValue,
            ];

            $previousTrack = $track;

            unset($track, $similarityValue);
        }

        return $tracks;
    }
}
