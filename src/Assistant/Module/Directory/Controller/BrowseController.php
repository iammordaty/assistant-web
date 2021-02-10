<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Common\Extension\PathBreadcrumbsGenerator;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\Track\Repository\TrackRepository;

// TODO: Uprościć przeglądarkę: ścieżki i katalogi w jednej tablicy
class BrowseController extends AbstractController
{
    public function index($guid = null)
    {
        $directory = (new DirectoryRepository($this->app->container['db']))
            ->findOneBy([ 'guid' => $guid ]);

        if ($directory === null) {
            $this->app->notFound();
        }

        $pathBreadcrumbs = PathBreadcrumbsGenerator::factory()->getPathBreadcrumbs($directory->pathname);

        return $this->app->render(
            '@directory/index.twig',
            [
                'menu' => 'browse',
                'directory' => $directory,
                'pathBreadcrumbs' => $pathBreadcrumbs,
                'childrens' => $this->getChildrens($directory),
            ]
        );
    }

    public function recent()
    {
        $getGroupName = function ($name) {
            $parts = explode(DIRECTORY_SEPARATOR, ltrim($name, '/'), 4);

            return sprintf('%s/%s', $parts[1] ?? '', $parts[2] ?? '');
        };

        $recent = [];

        $tracks = (new TrackRepository($this->app->container['db']))
            ->findBy([ ], [ 'limit' => 1000, 'sort' => [ 'indexed_date' => -1 ] ]);

        $repository = new DirectoryRepository($this->app->container['db']);

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

    public function incoming($pathname)
    {
        $incomingDefaultDir = '/_new';

        $absolutePathname = sprintf(
            '%s%s',
            $this->app->container->parameters['collection']['root_dir'],
            $pathname ?: $incomingDefaultDir
        );

        // TODO: Czy to muszą być dwie zmienne? Może da się to uprościć bez dużej straty w widoku
        $tracks = [];
        $directories = [];

        $reader = new ReaderFacade($this->app->container->parameters);

        $targetPathService = TargetPathService::factory();

        // TODO: Do zastanowienia się: po refaktoringu elementy powinny mieć dostęp do obiektów SplFileInfo
        foreach ($this->getIterator($absolutePathname) as $node) {
            $element = $reader->read($node);
            $targetPath = $targetPathService->getTargetPath($node);

            if ($node->isFile()) {
                $tracks[] = [
                    'track' => $element,
                    'node' => $node,
                    'targetPath' => $targetPath,
                ];
            } elseif ($node->isDir()) {
                $directories[] = [
                    'directory' => $element,
                    'node' => $node,
                    'targetPath' => $targetPath,
                ];
            }
        }

        sort($directories);

        usort($tracks, function ($data1, $data2) {
            return strnatcasecmp($data1['track']->pathname, $data2['track']->pathname);
        });

        $childrens = [
            'directories' => $directories,
            'tracks' => $tracks,
        ];

        $pathBreadcrumbs = array_map(function ($pathname) {
            return [
                'pathname' => DIRECTORY_SEPARATOR . $pathname,
                'displayName' => $pathname,
            ];
        }, explode(DIRECTORY_SEPARATOR, ltrim($pathname, DIRECTORY_SEPARATOR)));

        return $this->app->render(
            '@directory/incoming.twig',
            [
                'menu' => 'browse',
                'absolutePathname' => $absolutePathname,
                'pathname' => $pathname,
                'pathBreadcrumbs' => $pathBreadcrumbs,
                'childrens' => $childrens,
            ]
        );
    }

    /**
     * Zwraca elementy kolekcji znajdujące się w podanym katalogu
     *
     * @param Directory $directory
     * @return array
     */
    private function getChildrens(Directory $directory)
    {
        $directories = (new DirectoryRepository($this->app->container['db']))
            ->findBy(
                [ 'parent' => $directory->guid ],
                [ 'sort' => [ 'guid' => 1 ]]
            );

        $tracks = (new TrackRepository($this->app->container['db']))
            ->findBy([ 'parent' => $directory->guid ], [ 'sort' => [ 'guid' => 1 ]]);

        return [
            'directories' => iterator_to_array($directories),
            'tracks' => iterator_to_array($tracks),
        ];
    }

    /**
     * @param string $pathname
     * @return PathFilterIterator
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
