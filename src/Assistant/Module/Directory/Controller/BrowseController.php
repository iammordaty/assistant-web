<?php

namespace Assistant\Module\Directory\Controller;

use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator\BrowseCollectionRouteGenerator;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Track\Extension\TrackService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Slim\Views\Twig;

final class BrowseController
{
    public function __construct(
        private Config $config,
        private BreadcrumbsBuilder $breadcrumbsBuilder,
        private DirectoryService $directoryService,
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
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        $directory = $this->directoryService->getByGuid($guid);

        if (!$directory) {
            $guid = $this->slugify->slugify($this->config->get('collection.root_dir'));

            $route = Route::create('directory.browse.index')->withParams([ 'guid' => $guid ]);
            $redirectUrl = $this->routeResolver->resolve($route);

            return $response->withRedirect($redirectUrl);
        }

        $breadcrumbs = $this->breadcrumbsBuilder
            ->withPath($directory->getPathname())
            ->withRouteGenerator(new BrowseCollectionRouteGenerator())
            ->createBreadcrumbs();

        $directories = iterator_to_array($this->directoryService->getByDirectory($directory));
        $tracks = iterator_to_array($this->trackService->getByDirectory($directory));

        return $this->view->render($response, '@directory/index.twig', [
            'menu' => 'browse',
            'currentDirectory' => $directory,
            'breadcrumbs' => $breadcrumbs,
            'directories' => $directories,
            'tracks' => $tracks,
        ]);
    }
}
