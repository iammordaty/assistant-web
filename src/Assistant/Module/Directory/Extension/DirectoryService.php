<?php

namespace Assistant\Module\Directory\Extension;

use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Criteria\SearchSort;
use Assistant\Module\Search\Extension\Service\DirectorySearchService;
use Generator;

final class DirectoryService
{
    public function __construct(
        private DirectoryRepository $repository,
        private DirectorySearchService $searchService,
    ) {
    }

    public function getByGuid(string $guid): ?Directory
    {
        $criteria = SearchCriteriaFacade::createFromGuid($guid);

        return $this->searchService->findOne($criteria);
    }

    public function getByPathname(string $pathname): ?Directory
    {
        $criteria = SearchCriteriaFacade::createFromPathname($pathname);

        return $this->searchService->findOne($criteria);
    }

    public function save(Directory $directory): bool
    {
        return $this->repository->save($directory);
    }

    public function remove(Directory $directory): bool
    {
        return $this->repository->delete($directory);
    }

    /** @return Generator<int, Directory> */
    public function getByDirectory(Directory $directory): Generator
    {
        $criteria = SearchCriteriaFacade::createFromParent($directory->getGuid());

        return $this->searchService->search($criteria, SearchSort::byName());
    }
}
