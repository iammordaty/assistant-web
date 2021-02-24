<?php

use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\Slim\Views\Twig;
use Assistant\Module\Common\Extension\Twig\DurationTwigExtension;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\Track\Extension\TrackBuilder;
use Assistant\Module\Track\Repository\TrackRepository;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Slim;
use Slim\Views\TwigExtension as SlimTwigExtension;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Extra\String\StringExtension;
use MongoDB\Client as MongoDBClient;

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

$app->container->singleton('db', function () use ($app) {
    [ 'connection_uri' => $connectionUri, 'database' => $database ] = $app->container->servers['db'];

    $client = new MongoDBClient($connectionUri);
    $database = $client->selectDatabase($database);

    return $database;
});

$app->container->singleton('log', function () use ($app) {
    $logsDir = sprintf('%s/app/logs', $app->config('base_dir'));

    $logger = new Logger(sprintf('%s-logger', $app->getName()));

    $formatter = new LineFormatter('[%datetime%] %level_name% %message% %context% %extra%' . PHP_EOL);
    $formatter->ignoreEmptyContextAndExtra(true);

    $logger->setHandlers([
        (new StreamHandler(sprintf('%s/app.error.log', $logsDir), Logger::ERROR))->setFormatter($formatter),
        (new StreamHandler(sprintf('%s/app.debug.log', $logsDir), Logger::DEBUG))->setFormatter($formatter),
    ]);

    return $logger;
});

$app->container->singleton(TrackBuilder::class, function () use ($app) {
    $metadataParserParams = $app->container['parameters']['track']['metadata']['parser'];

    $fileReader = new FileReader(new Id3Adapter(), new MetadataParser($metadataParserParams));
    $builder = new TrackBuilder($fileReader, new TrackRepository($app->container['db']));

    return $builder;
});

$app->container->singleton(PathBreadcrumbs::class, function () {
    $pathBreadcrumbs = PathBreadcrumbs::factory();

    return $pathBreadcrumbs;
});
