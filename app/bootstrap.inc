<?php

/* @var $app \Slim\Slim */

// require enviroment config
use Assistant\Module\Common\Extension\Twig\CustomTwigExtension;
use Slim\Views\TwigExtension as SlimTwigExtension;
use Twig\Extension\Debug;
use Twig\Extension\DebugExtension as TwigDebugExtension;

require_once sprintf('%s/app/config/%s.inc', BASE_DIR, getenv('SLIM_MODE'));

// get application modules

$modules = [];

$iterator = new \DirectoryIterator(BASE_DIR . '/src/Assistant/Module/');

foreach ($iterator as $node) {
    if ($node->isDot() === true) {
        continue;
    }

    $module = substr($node, strrpos($node, '/'));
    $modules[$module] = $node->getPathname();

    unset($node);
}

unset($iterator, $node);

// require routes

foreach ($modules as $path) {
    $routesPath = sprintf('%s/Resources/routes/', $path);

    if (is_dir($routesPath) === false) {
        continue;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($routesPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST,
        \RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $node) {
        if ($node->isFile() && $node->getExtension() === 'inc') {
            require_once $node->getPathname();
        }

        unset($node);
    }

    unset($path, $routesPath, $iterator, $node);
}

// setup view

$view = new \Assistant\Module\Common\Extension\Slim\Views\Twig();

foreach ($modules as $name => $path) {
    $templatesPath = sprintf('%s/Resources/templates/', $path);

    if (is_dir($templatesPath)) {
        $view->loaderPathNamespaces[strtolower($name)] = $templatesPath;
    }

    unset($name, $path, $templatesPath);
}

$view->parserExtensions = [
    new SlimTwigExtension(),
    new CustomTwigExtension(),
    new \Twig_Extensions_Extension_Text(),
    new \Twig_Extensions_Extension_Date(),
];

if ($app->config('debug')) {
    $view->parserOptions['debug'] = true;

    $view->parserExtensions[] = new TwigDebugExtension();
}

$app->view($view);

unset($view);
