<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Mix\Extension\Strategy\MostSimilarTrackStrategy;
use Assistant\Module\Mix\Model\Mix as MixModel;
use Assistant\Module\Mix\Repository\MixRepository;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Model\Track;

final class MixService
{
    public const MAX_MIXES_PER_PAGE = 50;

    public function __construct(
        private MixRepository $repository,
        private SimilarityBuilder $similarityBuilder,
        private TrackSearchService $searchService,
    ) {
    }

    public function getByGuid(string $guid): ?MixModel
    {
        $mix = $this->repository->findOneBy([ 'guid' => $guid ]);

        if (!$mix) {
            return null;
        }

        foreach ($mix->attempts as $attempt) {
            foreach ($attempt->trackList as $trackEntry) {
                $trackEntry->track = $this->searchService->findOneByGuid($trackEntry->trackGuid);
            }
        }

        return $mix;
    }

    public function getMixes(int $page = 1): array
    {
        $skip = ($page - 1) * self::MAX_MIXES_PER_PAGE;

        $mixes = $this->repository->findBy(
            limit: self::MAX_MIXES_PER_PAGE,
            skip: $skip,
            sort: [ 'modified' => Storage::SORT_DESC ],
        );

        $count = $this->repository->count();

        return [
            'mixes' => $mixes,
            'count' => $count,
        ];
    }

    public function save(MixModel $mix): MixModel
    {
        $result = $this->repository->save($mix);

        if (!$result) {
            throw new \Exception('Failed to save mix');
        }

        return $this->getByGuid($mix->guid);
    }

    /**
     * Wrzucone na yolo, nie przywiązywać się
     *
     * @param string[] $listing
     * @return array
     */
    public function getMixInfo(array $listing): array
    {
        $listing = array_map('trim', $listing);
        $tracks = $this->getTracks($listing); // @idea: być może to powinno być wyżej

        $similarityService = $this->similarityBuilder->createService()->getSimilarityService();
        $strategy = new MostSimilarTrackStrategy($similarityService);

        // @todo: dodać strategię, która dobierze spoza listingu najbardziej podobny następny kawałek (także do kolejnego),
        //        jeśli najlepiej różnica do następnego będzie większa od zadanej

        $arrangedMix = new HarmonicMix($strategy, $tracks);

        $mix = $arrangedMix->getMix();
        $similarityGrid = $arrangedMix->getSimilarityGrid();

        return [ $mix, $similarityGrid ];
    }

    /**
     * @param string[] $listing
     * @return Track[]
     */
    private function getTracks(array $listing): array
    {
        $tracks = [];

        foreach ($listing as $trackName) {
            if ($trackName === '') {
                continue;
            }

            $track = $this->searchService->findOneByName($trackName);

            if (!$track) {
                // @todo: brak wyszukanego utworu powinien być komunikowany na froncie
                continue;
            }

            $tracks[] =  $track;

            unset($track, $trackName);
        }

        return $tracks;
    }
}
