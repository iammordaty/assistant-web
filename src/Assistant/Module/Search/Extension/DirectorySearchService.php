<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Traversable;

/** Lokalizacja tymczasowa / wrzucone na szybko; przemyśleć, nie przywiązywać się */
final class DirectorySearchService
{
    public function __construct(private DirectoryRepository $directoryRepository)
    {
    }

    public function findOneByGuid(string $guid): ?Directory
    {
        $searchCriteria = SearchCriteriaFacade::createFromGuid($guid);
        $directory = $this->directoryRepository->getOneBy($searchCriteria);

        return $directory;
    }

    public function findOneByPathname(string $pathname): ?Directory
    {
        $searchCriteria = SearchCriteriaFacade::createFromPathname($pathname);
        $directory = $this->directoryRepository->getOneBy($searchCriteria);

        return $directory;
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param array|null $sort
     * @param int|null $limit
     * @param int|null $skip
     * @return Directory[]|Traversable
     */
    public function findBy(
        SearchCriteria $searchCriteria,
        ?array $sort = null,
        ?int $limit = null,
        ?int $skip = null
    ): array|Traversable {
        $tracks = $this->directoryRepository->getBy($searchCriteria, $sort, $limit, $skip);

        return $tracks;
    }
}
