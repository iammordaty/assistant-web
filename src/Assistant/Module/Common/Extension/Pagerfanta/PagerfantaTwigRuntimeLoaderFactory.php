<?php

namespace Assistant\Module\Common\Extension\Pagerfanta;

use Assistant\Module\Common\Extension\RouteResolver;
use Pagerfanta\Twig\Extension\PagerfantaRuntime;
use Pagerfanta\View\Template\TwitterBootstrap5Template;
use Pagerfanta\View\TwitterBootstrap5View;
use Pagerfanta\View\ViewFactory;
use Psr\Container\ContainerInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class PagerfantaTwigRuntimeLoaderFactory
{
    public static function createRuntimeLoader(ContainerInterface $container): FactoryRuntimeLoader
    {
        $view = new TwitterBootstrap5View(new TwitterBootstrap5Template());

        $viewFactory = new ViewFactory();
        $viewFactory->add([ $view->getName() => $view ]);

        $pagerfantaRuntimeTwigExtension = new PagerfantaRuntime(
            $view->getName(),
            $viewFactory,
            new RouteGeneratorFactory($container->get(RouteResolver::class))
        );

        $runtimeLoader = new FactoryRuntimeLoader([
            $pagerfantaRuntimeTwigExtension::class => fn () => $pagerfantaRuntimeTwigExtension,
        ]);

        return $runtimeLoader;
    }
}
