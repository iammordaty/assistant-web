<?php

// Wrzucone na szybko, być może powinno leżeć bliżej modelu
namespace Assistant\Module\Directory\Extension;

use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\DirectorySearchService;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Traversable;

// @idea: Być może należałoby rozdzielić klasę DirectoryService zajmującą się pojedynczymi utworami od
//        klasy zajmującej się listą (vide getByDirectory, getRecent)
final class DirectoryService
{
    public function __construct(
        private DirectoryRepository $repository,
        private DirectorySearchService $searchService,
    ) {
    }

    public function getByGuid(string $guid): ?Directory
    {
        return $this->searchService->findOneByGuid($guid);
    }

    public function getByPathname(string $pathname): ?Directory
    {
        return $this->searchService->findOneByPathname($pathname);
    }

    public function save(Directory $track): bool
    {
        $result = $this->repository->save($track);

        return $result;
    }

    public function remove(Directory $directory): bool
    {
        $result = $this->repository->delete($directory);

        return $result;
    }

    /**
     * @param Directory $directory
     * @return Directory[]|Traversable
     */
    public function getByDirectory(Directory $directory): array|Traversable
    {
        $searchCriteria = SearchCriteriaFacade::createFromParent($directory->getGuid());

        $tracks = $this->searchService->findBy(
            $searchCriteria,
            [ 'guid' => Storage::SORT_ASC ]
        );

        return $tracks;
    }
}
