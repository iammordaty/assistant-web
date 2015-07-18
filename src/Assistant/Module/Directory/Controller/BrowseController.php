<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Controller as BaseController;
use Assistant\Module\Common;
use Assistant\Module\Directory;
use Assistant\Module\Track;

class BrowseController extends BaseController
{
    use Common\Extension\Traits\GetPathBreadcrumbs;

    public function index($guid = null)
    {
        $directory = (new Directory\Repository\DirectoryRepository($this->app->container['db']))->findOneByGuid($guid);

        if ($directory === null) {
            $this->app->notFound();
        }

        return $this->app->render(
            '@directory/index.twig',
            [
                'menu' => 'browse',
                'directory' => $directory,
                'pathBreadcrumbs' => $this->getPathBreadcrumbs($directory->pathname),
                'childrens' => $this->getChildrens($directory),
            ]
        );
    }

    /**
     * Zwraca elementy kolekcji znajdujące się w podanym katalogu
     *
     * @param \Assistant\Module\Directory\Model\Directory $directory
     * @return array
     */
    private function getChildrens(Directory\Model\Directory $directory)
    {
        $directories = (new Directory\Repository\DirectoryRepository($this->app->container['db']))
            ->findBy(
                [ 'parent' => $directory->guid ],
                [ ],
                [ 'sort' => [ 'guid' => 1 ]]
            );

        $tracks = (new Track\Repository\TrackRepository($this->app->container['db']))
            ->findBy([ 'parent' => $directory->guid ], [ ], [ 'sort' => [ 'guid' => 1 ]]);

        return [
            'directories' => $directories,
            'tracks' => $tracks,
        ];
    }
}
