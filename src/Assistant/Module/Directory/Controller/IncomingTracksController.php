<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator\BrowseIncomingRouteGenerator;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\TargetPathService;
use Assistant\Module\Directory\Model\Directory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\Twig;
use SplFileInfo;

final class IncomingTracksController
{
    public function __construct(
        private BreadcrumbsBuilder $breadcrumbsBuilder,
        private Config $config,
        private ReaderFacade $reader,
        private RouteResolver $routeResolver,
        private SlugifyService $slugify,
        private Twig $view,
    ) {
    }

    public function index(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $pathname = $request->getAttribute('pathname') ?: $this->config->get('collection.incoming_dir');

        if (!is_readable($pathname)) {
            $guid = $this->slugify->slugify($this->config->get('collection.incoming_dir'));

            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        $breadcrumbs = $this->breadcrumbsBuilder
            ->withPath($pathname)
            ->withRouteGenerator(new BrowseIncomingRouteGenerator())
            ->createBreadcrumbs();

        [ $tracks, $directories ] = $this->getCollectionItems($pathname);

        return $this->view->render($response, '@directory/incoming.twig', [
            'menu' => 'browse',
            'pathname' => $pathname,
            'breadcrumbs' => $breadcrumbs,
            'directories' => $directories,
            'tracks' => $tracks,
        ]);
    }

    private function getCollectionItems(mixed $pathname): array
    {
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

        return [ $tracks, $directories ];
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
