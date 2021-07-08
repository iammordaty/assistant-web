<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use SplFileInfo;

final class BrowseController
{
    public function __construct(
        private Config $config,
        private DirectoryRepository $directoryRepository,
        private PathBreadcrumbs $pathBreadcrumbs,
        private ReaderFacade $reader,
        private RouteResolver $routeResolver,
        private SlugifyService $slugify,
        private TrackRepository $trackRepository,
        private Twig $view,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $guid = $request->getAttribute('guid');

        if (!$guid) {
            $guid = $this->slugify->slugify($this->config->get('collection.root_dir'));

            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(302);

            return $redirect;
        }

        $directory = $this->directoryRepository->getByGuid($guid);

        if (!$directory) {
            $guid = $this->slugify->slugify($this->config->get('collection.root_dir'));

            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

            return $redirect;
        }

        $directories = iterator_to_array($this->directoryRepository->getChildren($directory));
        $tracks = iterator_to_array($this->trackRepository->getChildren($directory));

        return $this->view->render($response, '@directory/index.twig', [
            'menu' => 'browse',
            'currentDirectory' => $directory,
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($directory->getPathname()),
            'directories' => $directories,
            'tracks' => $tracks,
        ]);
    }

    public function recent(Request $request, Response $response): Response
    {
        $getGroupName = static function ($name): string {
            $parts = explode(DIRECTORY_SEPARATOR, ltrim($name, '/'));

            return sprintf('%s/%s', $parts[2], $parts[3]);
        };

        $recent = [];

        foreach ($this->trackRepository->getRecent() as $track) {
            $groupName = $getGroupName($track->getPathname());

            if (isset($recent[$groupName]) === false) {
                $recent[$groupName] = [
                    'name' => $groupName,
                    'tracks' => [],
                ];
            }

            $recent[$groupName]['tracks'][$track->getGuid()] = $track;
        }

        foreach ($recent as &$group) {
            ksort($group['tracks']);
        }

        return $this->view->render($response, '@directory/recent.twig', [
            'menu' => 'browse',
            'recent' => $recent,
        ]);
    }

    public function incoming(Request $request, Response $response): Response
    {
        $pathname = $request->getAttribute('pathname') ?: $this->config->get('collection.incoming_dir');

        if (!is_readable($pathname)) {
            $guid = $this->slugify->slugify($this->config->get('collection.incoming_dir'));

            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(404);

            return $redirect;
        }

        $tracks = [];
        $directories = [];

        $targetPathService = TargetPathService::factory();

        foreach ($this->getNodes($pathname) as $node) {
            /** @var CollectionItemInterface $collectionItem */
            $collectionItem = $this->reader->read($node);
            $targetPath = $targetPathService->getTargetPath($node);

            if ($node->isFile()) {
                $tracks[] = [
                    'collectionItem' => $collectionItem,
                    'targetPath' => $targetPath,
                ];
            } elseif ($node->isDir()) {
                $directories[] = [
                    'collectionItem' => $collectionItem,
                    'targetPath' => $targetPath,
                ];
            }
        }

        /** @uses Directory::getName() */
        usort($directories, static function ($data1, $data2): int {
            return strnatcasecmp($data1['collectionItem']->getName(), $data2['collectionItem']->getName());
        });

        /** @uses IncomingTrack::getName() */
        usort($tracks, static function ($data1, $data2): int {
            return strnatcasecmp($data1['collectionItem']->getName(), $data2['collectionItem']->getName());
        });

        return $this->view->render($response, '@directory/incoming.twig', [
            'menu' => 'browse',
            'pathname' => $pathname,
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($pathname),
            'directories' => $directories,
            'tracks' => $tracks,
        ]);
    }

    /**
     * @param string $pathname
     * @return SplFileInfo[]|Finder
     */
    private function getNodes(string $pathname): array|Finder
    {
        return Finder::create([
            'pathname' => $pathname,
            'recursive' => false,
            'skip_self' => true,
        ]);
    }
}
