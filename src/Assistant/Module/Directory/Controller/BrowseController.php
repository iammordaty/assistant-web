<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Extension\TrackService;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\Twig;
use SplFileInfo;

final class BrowseController
{
    public function __construct(
        private Config $config,
        private DirectoryService $directoryService,
        private PathBreadcrumbs $pathBreadcrumbs,
        private ReaderFacade $reader,
        private RouteResolver $routeResolver,
        private SlugifyService $slugify,
        private TrackService $trackService,
        private Twig $view,
    ) {
    }

    public function index(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $guid = $request->getAttribute('guid');

        if (!$guid) {
            $guid = $this->slugify->slugify($this->config->get('collection.root_dir'));
            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);

            $redirect = $response
                ->withRedirect($this->routeResolver->resolve($route))
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $redirect;
        }

        $directory = $this->directoryService->getByGuid($guid);

        if (!$directory) {
            $guid = $this->slugify->slugify($this->config->get('collection.root_dir'));
            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);

            $redirect = $response
                ->withRedirect($this->routeResolver->resolve($route))
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $redirect;
        }

        $directories = iterator_to_array($this->directoryService->getByDirectory($directory));
        $tracks = iterator_to_array($this->trackService->getByDirectory($directory));

        return $this->view->render($response, '@directory/index.twig', [
            'menu' => 'browse',
            'currentDirectory' => $directory,
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($directory->getPathname()),
            'directories' => $directories,
            'tracks' => $tracks,
        ]);
    }

    public function recent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $getGroupName = static function ($name): string {
            $parts = explode(DIRECTORY_SEPARATOR, ltrim($name, '/'));

            return sprintf('%s/%s', $parts[2], $parts[3]);
        };

        $recent = [];

        foreach ($this->trackService->getRecent() as $track) {
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

    public function incoming(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname') ?: $this->config->get('collection.incoming_dir');

        if (!is_readable($pathname)) {
            $guid = $this->slugify->slugify($this->config->get('collection.incoming_dir'));
            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);

            $redirect = $response
                ->withRedirect($this->routeResolver->resolve($route))
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

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
