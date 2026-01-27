<?php

namespace Assistant\Module\Search\Extension\Service;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Generator;

final class DirectorySearchService
{
    public function __construct(private DirectoryRepository $directoryRepository)
    {
    }

    public function findOne(SearchCriteria $criteria, ?SearchSort $searchSort = null): ?Directory
    {
        return $this->directoryRepository->getOneBy($criteria, $searchSort);
    }

    /** @return Generator<int, Directory> */
    public function search(
        SearchCriteria $criteria,
        ?SearchSort $sort = null,
        ?int $limit = null,
        ?int $skip = null,
    ): Generator {
        $directories = $this->directoryRepository->getBy($criteria, $sort, $limit, $skip);

        return $directories;
    }
}
