<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use Cocur\Slugify\Slugify;
use Slim\Slim;
use SplFileInfo;

// TODO: Uprościć przeglądarkę: ścieżki i katalogi w jednej tablicy
class BrowseController extends AbstractController
{
    private DirectoryRepository $directoryRepository;

    private TrackRepository $trackRepository;

    public function __construct(Slim $app)
    {
        parent::__construct($app);

        $this->directoryRepository = new DirectoryRepository($app->container['db']);
        $this->trackRepository = new TrackRepository($app->container['db']);
    }

    public function index($guid = null)
    {
        if (!$guid) {
            $slugify = new Slugify();

            $guid = $slugify->slugify($this->app->container['parameters']['collection']['root_dir']);
            $redirectUrl = $this->app->urlFor('directory.browse.index', [ 'guid' => $guid ]);

            $this->app->redirect($redirectUrl);
        }

        $directory = $this->directoryRepository->findOneByGuid($guid);

        if ($directory === null) {
            $this->app->notFound();
        }

        $pathBreadcrumbs = $this->app->container[PathBreadcrumbs::class]->get($directory->pathname);

        return $this->app->render('@directory/index.twig', [
            'menu' => 'browse',
            'directory' => $directory,
            'pathBreadcrumbs' => $pathBreadcrumbs,
            'children' => $this->getChildren($directory),
        ]);
    }

    public function recent()
    {
        $getGroupName = static function ($name): string {
            $parts = explode(DIRECTORY_SEPARATOR, ltrim($name, '/'));

            return sprintf('%s/%s', $parts[2], $parts[3]);
        };

        $recent = [];

        foreach ($this->trackRepository->getRecentTracks() as $track) {
            $groupName = $getGroupName($track->getPathname());

            if (isset($recent[$groupName]) === false) {
                $recent[$groupName] = [
                    'name' => $groupName,
                    'tracks' => [],
                ];
            }

            $recent[$groupName]['tracks'][$track->getGuid()] = $track;
        }

        krsort($recent);

        foreach ($recent as &$group) {
            ksort($group['tracks']);
        }

        return $this->app->render('@directory/recent.twig', [
            'menu' => 'browse',
            'recent' => $recent,
        ]);
    }

    public function incoming($pathname)
    {
        if (!$pathname) {
            $pathname = $this->app->container['parameters']['collection']['incoming_dir'];
        }

        // TODO: Czy to muszą być dwie zmienne? Może da się to uprościć bez dużej straty w widoku
        $tracks = [];
        $directories = [];

        $reader = ReaderFacade::factory($this->app->container);
        $targetPathService = TargetPathService::factory();

        foreach ($this->getNodes($pathname) as $node) {
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

        usort($tracks, static fn($data1, $data2): int => (
            strnatcasecmp($data1['track']->pathname, $data2['track']->pathname))
        );

        $children = [
            'directories' => $directories,
            'tracks' => $tracks,
        ];

        $pathBreadcrumbs = $this->app->container[PathBreadcrumbs::class]->get($pathname);

        return $this->app->render('@directory/incoming.twig', [
            'menu' => 'browse',
            'pathname' => $pathname,
            'pathBreadcrumbs' => $pathBreadcrumbs,
            'children' => $children,
        ]);
    }

    /**
     * Zwraca elementy kolekcji znajdujące się w podanym katalogu
     *
     * @param Directory $directory
     * @return array
     */
    private function getChildren(Directory $directory): array
    {
        $directories = $this->directoryRepository->findBy(
            [ 'parent' => $directory->guid ],
            [ 'sort' => [ 'guid' => 1 ]]
        );

        $tracks = $this->trackRepository->findBy(
            [ 'parent' => $directory->guid ],
            [ 'sort' => [ 'guid' => 1 ]]
        );

        return [
            'directories' => iterator_to_array($directories),
            'tracks' => iterator_to_array($tracks),
        ];
    }

    /**
     * @param string $pathname
     * @return Finder|SplFileInfo[]
     */
    private function getNodes(string $pathname)
    {
        return Finder::create([
            'pathname' => $pathname,
            'recursive' => false,
            'skip_self' => true,
        ]);
    }
}
