<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Common;
use Assistant\Module\Directory;
use Assistant\Module\Collection;
use Assistant\Module\Track;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\SplFileInfo;

// TODO: Uprościć przeglądarkę: ścieżki i katalogi w jednej tablicy
class BrowseController extends Common\Controller\AbstractController
{
    use Common\Extension\Traits\GetPathBreadcrumbs,
        Common\Extension\Traits\GetTargetPath;

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

    public function incoming($pathname) {
        $absolutePathname = sprintf(
            '%s%s',
            $this->app->container->parameters['collection']['root_dir'],
            $pathname
        );

        // TODO: Czy to muszą być dwie zmienne? Może da się to uprościć bez dużej straty w widoku
        $tracks = [];
        $directories = [];

        $processor = new Collection\Extension\Processor\Processor($this->app->container->parameters);

        // TODO: Do zastanowienia się: po refaktoringu elementy powinny mieć dostęp do obiektów SplFileInfo
        foreach ($this->getIterator($absolutePathname) as $node) {
            $element = $processor->process($node);
            $targetPath = $this->getTargetPath($node);

            if ($node->isFile()) {
                $tracks[] = [
                    'track' => $element,
                    'node' => $node,
                    'targetPath' => $targetPath,
                ];
            } else if ($node->isDir()) {
                $directories[] = [
                    'directory' => $element,
                    'node' => $node,
                    'targetPath' => $targetPath,
                ];
            }
        }

        echo '<!--<pre>';
        foreach ($directories as $data) {
            echo $data['directory']->pathname, ' - ', $data['targetPath'], PHP_EOL;
        }
        foreach ($tracks as $data) {
            echo $data['track']->pathname, ' - ', $data['targetPath'], PHP_EOL;
        }
        echo '</pre>-->';

        usort($tracks, function($data1, $data2) {
        	return strnatcasecmp($data1['track']->pathname, $data2['track']->pathname);
        });

        $childrens = [
            'directories' => $directories,
            'tracks' => $tracks,
        ];

        return $this->app->render(
            '@directory/incoming.twig',
            [
                'menu' => 'browse',
                'directory' => $absolutePathname,
                'pathBreadcrumbs' => explode(DIRECTORY_SEPARATOR, ltrim($absolutePathname, DIRECTORY_SEPARATOR)),
                'childrens' => $childrens,
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

    /**
     * @param string $pathname
     * @return IgnoredPathIterator
     */
    private function getIterator($pathname)
    {
        return new PathFilterIterator(
            new RecursiveDirectoryIterator($pathname, RecursiveDirectoryIterator::SKIP_DOTS),
            $this->app->container->parameters['collection']['root_dir'],
            [ '@eaDir' ]
        );
    }
}
