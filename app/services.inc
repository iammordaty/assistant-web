<?php

use Assistant\Module\Common\Extension\Slim\Views\Twig;
use Assistant\Module\Common\Extension\Twig\DurationTwigExtension;
use Slim\Slim;
use Slim\Views\TwigExtension as SlimTwigExtension;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Extra\String\StringExtension;

/* @var $app Slim */

// setup view

$iterator = new \FilesystemIterator(BASE_DIR . '/src/Assistant/Module/', \FilesystemIterator::SKIP_DOTS);

$view = new Twig();

foreach ($iterator as $node) {
    $name = strtolower($node->getFilename());
    $templatesPath = sprintf('%s/Resources/templates/', $node->getPathname());

    if (is_dir($templatesPath)) {
        $view->loaderPathNamespaces[$name] = $templatesPath;
    }

    unset($name, $templatesPath);
}

unset($iterator);

$view->parserExtensions = [
    DurationTwigExtension::factory(),
    new SlimTwigExtension(),
    new StringExtension(),
];

if ($app->config('debug')) {
    $view->parserOptions['debug'] = true;

    $view->parserExtensions[] = new TwigDebugExtension();
}

$app->view($view);

unset($view);
