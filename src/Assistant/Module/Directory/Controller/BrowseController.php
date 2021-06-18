<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Common\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use SplFileInfo;

// TODO: Uprościć przeglądarkę: ścieżki i katalogi w jednej tablicy
final class BrowseController
{
    public function __construct(
        private Config $config,
        private DirectoryRepository $directoryRepository,
        private PathBreadcrumbs $pathBreadcrumbs,
        private ReaderFacade $reader,
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

            $routeName = 'directory.browse.index';
            $data = [ 'guid' => $guid ];
            $queryParams = [ ];

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $redirectUrl = $routeParser->urlFor($routeName, $data, $queryParams);

            $redirect = $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(302);

            return $redirect;
        }

        $directory = $this->directoryRepository->getByGuid($guid);

        if (!$directory) {
            throw new HttpNotFoundException($request);
        }

        return $this->view->render($response, '@directory/index.twig', [
            'menu' => 'browse',
            'directory' => $directory,
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($directory->getPathname()),
            'children' => $this->getChildren($directory),
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
            throw new HttpNotFoundException($request);
        }

        // TODO: Czy to muszą być dwie zmienne? Może da się to uprościć bez dużej straty w widoku
        $tracks = [];
        $directories = [];

        $targetPathService = TargetPathService::factory();

        foreach ($this->getNodes($pathname) as $node) {
            /** @var CollectionItemInterface $element */
            $element = $this->reader->read($node);
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

        /** @uses Track::getPathname() */
        usort($tracks, static function ($data1, $data2): int {
            return strnatcasecmp($data1['track']->getPathname(), $data2['track']->getPathname());
        });

        $children = [
            'directories' => $directories,
            'tracks' => $tracks,
        ];

        return $this->view->render($response, '@directory/incoming.twig', [
            'menu' => 'browse',
            'pathname' => $pathname,
            'pathBreadcrumbs' => $this->pathBreadcrumbs->get($pathname),
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
        $directories = $this->directoryRepository->getChildren($directory);
        $tracks = $this->trackRepository->getChildren($directory);

        return [
            'directories' => iterator_to_array($directories),
            'tracks' => iterator_to_array($tracks),
        ];
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
