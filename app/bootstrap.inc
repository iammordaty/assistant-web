<?php

/* @var $app \Slim\Slim */

// require enviroment config
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
    new \Slim\Views\TwigExtension(),
    new \Assistant\Module\Common\Extension\Twig\CustomTwigExtension(),
];

if ($app->config('debug')) {
    $view->parserOptions['debug'] = true ;
    $view->parserExtensions[] = new \Twig_Extension_Debug();
}

$app->view($view);

unset($view);
