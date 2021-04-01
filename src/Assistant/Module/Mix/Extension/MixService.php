<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Mix\Extension\Strategy\MostSimilarTrackStrategy;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\SimilarTracksVO;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Slim\Helper\Set as Container;

final class MixService
{
    private Similarity $similarity;

    private TrackService $trackService;

    public function __construct(Similarity $similarity, TrackService $trackService)
    {
        $this->similarity = $similarity;
        $this->trackService = $trackService;
    }

    public static function factory(Container $container): self
    {
        $similarity = new Similarity(
            $container[TrackRepository::class],
            $container['parameters']['track']['similarity']
        );

        return new self($similarity, $container[TrackService::class]);
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
            $track = $this->trackService->getTrackByName($trackName);

            if (!$track) {
                // @todo: brak wyszukanego utworu powinien być komunikowany na froncie
                continue;
            }

            $tracks[] = [
                'track' => $track,
                'similarityValue' => $previousTrack ? $this->similarity->getSimilarityValue($previousTrack, $track) : null
            ];

            $previousTrack = $track;
        }

        return $tracks;
    }
}
