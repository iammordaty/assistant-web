<?php

namespace Assistant\Module\Mix\Extension;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Mix\Extension\Mix\AttemptSaveRequest;
use Assistant\Module\Mix\Extension\Mix\MixSaveRequest;
use Assistant\Module\Mix\Extension\Strategy\MostSimilarTrackStrategy;
use Assistant\Module\Mix\Model\Attempt;
use Assistant\Module\Mix\Model\AttemptDto;
use Assistant\Module\Mix\Model\Mix;
use Assistant\Module\Mix\Repository\MixRepository;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Model\Track;
use Cocur\Slugify\Slugify;

final class MixService
{
    public const int MAX_MIXES_PER_PAGE = 50;

    public function __construct(
        private MixRepository $repository,
        private SimilarityBuilder $similarityBuilder,
        private TrackSearchService $searchService,
    ) {
    }

    public function getByGuid(string $guid): ?Mix
    {
        $mix = $this->repository->findOneBy([ 'guid' => $guid ]);

        if (!$mix) {
            return null;
        }

        $this->hydrate($mix);

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

    public function createNewMix(): Mix
    {
        $mix = Mix::create(
            guid: '',
            name: 'Nowy miks',
        );

        return $mix;
    }

    public function saveMix(?Mix $mix, MixSaveRequest $request): Mix
    {
        if (!$mix) {
            $mix = $this->createNewMix();
        }

        $updatedMix = Mix::create(
            guid: $this->getUniqueGuid($mix->guid, $request->name),
            name: $request->name,
            description: $request->description,
            created: $request->created,
            modified: $request->modified,
            performed: $request->performed,
            comment: $request->comment,
            attempts: $mix->attempts,
        );

        $this->repository->save($mix, $updatedMix);

        return $updatedMix;
    }

    public function saveAttempt(Mix $mix, AttemptSaveRequest $attemptRequest): Mix
    {
        $updatedAttempt = Attempt::fromDto(AttemptDto::fromRequest($attemptRequest));

        $attempts = $mix->attempts;

        if ($attemptRequest->id) {
            $attempts = array_map(
                fn (Attempt $attempt) => $attempt->id === $attemptRequest->id ? $updatedAttempt : $attempt,
                $attempts
            );
        }  else {
            $attempts[] = $updatedAttempt;
        }

        $updatedMix = Mix::create(
            guid: $mix->guid,
            name: $mix->name,
            description: $mix->description,
            created: $mix->created,
            modified: $mix->modified,
            performed: $mix->performed,
            comment: $mix->comment,
            attempts: $attempts
        );

        $this->repository->save($mix, $updatedMix);

        foreach ($updatedMix->attempts as $attempt) {
            foreach ($attempt->trackList as $trackEntry) {
                $trackEntry->track = $this->searchService->findOneByGuid($trackEntry->trackGuid);
            }
        }

        return $updatedMix;
    }

    public function deleteMix(Mix $mix): bool
    {
        return $this->repository->delete($mix);
    }

    private function getUniqueGuid(?string $currentGuid, string $name): string
    {
        $slug = (new Slugify())->slugify($name);

        if ($slug === $currentGuid) {
            return $currentGuid;
        }

        $isAvailable = fn (string $guid): bool =>
            !$this->repository->findOneBy([ 'guid' => $guid ])
            || $guid === $currentGuid;

        if ($isAvailable($slug)) {
            return $slug;
        }

        $number = 2;

        while (!$isAvailable(sprintf('%s-%s', $slug, $number))) {
            $number++;
        }

        return sprintf('%s-%s', $slug, $number);
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

    /**
     * @idea: Tu przydałoby się zebrać wszystkie guid-y i wykonać jedno zapytanie do bazy,
     *        ale obecnie nie ma takiej możliwości w SearchCriteria. Do dorobienia.
     */
    private function hydrate(Mix $mix): void
    {
        foreach ($mix->attempts as $attempt) {
            foreach ($attempt->trackList as $trackEntry) {
                $trackEntry->track = $this->searchService->findOneByGuid($trackEntry->trackGuid);
            }
        }
    }
}
