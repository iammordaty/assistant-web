<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Common;
use Assistant\Module\Directory;
use Assistant\Module\Track;

class BrowseController extends Common\Controller\AbstractController
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
    
    public function recent()
    {
        $getGroupName = function ($name) {
            $parts = explode(DIRECTORY_SEPARATOR, ltrim($name, '/'), 4);

            return sprintf('%s/%s', $parts[1], $parts[2]);
        };

        $recent = [];

        $tracks = (new Track\Repository\TrackRepository($this->app->container['db']))
            ->findBy([ ], [ ], [ 'limit' => 500, 'sort' => [ 'modified_date' => -1 ] ]);

        $repository = new Directory\Repository\DirectoryRepository($this->app->container['db']);

        foreach ($tracks as $track) {
            $key = $getGroupName($track->parent);

            if (isset($recent[$key]) === false) {
                $recent[$key] = [
                    'name' => $getGroupName($repository->findOneByGuid($track->parent)->pathname),
                    'tracks' => [],
                ];
            }

            $recent[$key]['tracks'][$track->guid] = $track;
        }

        krsort($recent);

        foreach ($recent as &$group) {
            ksort($group['tracks']);
        }

        return $this->app->render(
            '@directory/recent.twig',
            [
                'menu' => 'browse',
                'recent' => $recent,
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
            'directories' => iterator_to_array($directories),
            'tracks' => iterator_to_array($tracks),
        ];
    }
}
