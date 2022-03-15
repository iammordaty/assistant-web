<?php

use Assistant\Module\Collection\Extension\Autocomplete\TrackAutocompleteService;
use Assistant\Module\Collection\Extension\Reader\DirectoryReader;
use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Collection\Extension\Reader\MetadataParser\MetadataParser;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\Twig\DurationTwigExtension;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Mix\Extension\MixService;
use Assistant\Module\Search\Extension\DirectorySearchService;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Similarity;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Extension\TrackLocationArbiter;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use MongoDB\Client as MongoDBClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface as Container;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Extra\String\StringExtension;

use function DI\autowire;
use function DI\create;

return static fn (string $baseDir, array $config): array => [
    Config::class => create()->constructor($config),

    DirectoryReader::class => autowire(),

    DirectoryRepository::class => function (Container $container): DirectoryRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = DirectoryRepository::factory($database);

        return $repository;
    },

    DirectoryService::class => autowire(),

    DirectorySearchService::class => autowire(),

    FileReader::class => function (Container $container): FileReader {
        $metadataParserParams = $container->get(Config::class)->get('track_metadata_parser');

        $fileReader = new FileReader(
            $container->get(Id3Adapter::class),
            new MetadataParser($metadataParserParams),
            $container->get(SlugifyService::class),
        );

        return $fileReader;
    },

    FileReaderFacade::class => autowire(),

    Id3Adapter::class => create(),

    Logger::class => function () use ($baseDir): LoggerInterface {
        $logsDir = $baseDir . '/var/logs';

        $logger = new Logger('assistant-logger');

        $formatter = new LineFormatter('[%datetime%] %level_name% %message% %context% %extra%' . PHP_EOL);
        $formatter->ignoreEmptyContextAndExtra(true);

        $logger->setHandlers([
            (new ErrorLogHandler())->setFormatter(new LineFormatter(
                '[%datetime%] [%level_name%] %message% %context%',
                'd.m.Y, H:i:s',
                false,
                true
            )),
            (new StreamHandler(sprintf('%s/app.error.log', $logsDir), Logger::ERROR))->setFormatter($formatter),
            (new StreamHandler(sprintf('%s/app.debug.log', $logsDir), Logger::DEBUG))->setFormatter($formatter),
        ]);

        return $logger;
    },

    MixService::class => autowire(),

    PathBreadcrumbs::class => autowire(),

    ReaderFacade::class => autowire(),

    RouteResolver::class => function (Container $container): RouteResolver {
        $routeParser = $container->get(App::class)->getRouteCollector()->getRouteParser();
        $urlResolver = new RouteResolver($routeParser);

        return $urlResolver;
    },

    SimilarityBuilder::class => function (Container $container): SimilarityBuilder {
        $parameters = $container->get(Config::class)->get(Similarity::class);

        return new SimilarityBuilder(
            $container->get(TrackSearchService::class),
            $container->get(SimilarTracksCollectionService::class),
            $parameters['providers_parameters'],
            $parameters['providers_weights'],
            $parameters['min_similarity_value'],
            $parameters['max_tracks'],
        );
    },

    SimilarTracksCollectionService::class => autowire(),

    SlugifyService::class => create(),

    TrackAutocompleteService::class => autowire(),

    TrackLocationArbiter::class => autowire(),

    TrackRepository::class => function (Container $container): TrackRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = TrackRepository::factory($database);

        return $repository;
    },

    TrackSearchService::class => autowire(),

    TrackService::class => autowire(),

    TrackStatsRepository::class => function (Container $container): TrackStatsRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = TrackStatsRepository::factory($database);

        return $repository;
    },

    Twig::class => function () use ($baseDir): Twig {
        $paths = [
            'common' => $baseDir . '/src/Assistant/Module/Common/Resources/templates/',
            'dashboard' => $baseDir . '/src/Assistant/Module/Dashboard/Resources/templates/',
            'directory' => $baseDir . '/src/Assistant/Module/Directory/Resources/templates/',
            'mix' => $baseDir . '/src/Assistant/Module/Mix/Resources/templates/',
            'search' => $baseDir . '/src/Assistant/Module/Search/Resources/templates/',
            'track' => $baseDir . '/src/Assistant/Module/Track/Resources/templates/',
        ];

        $settings = [
            'debug' => true,
            'strict_variables' => true,
        ];

        $view = Twig::create($paths, $settings);

        $view->addExtension(DurationTwigExtension::factory());
        $view->addExtension(new TwigDebugExtension());
        $view->addExtension(new StringExtension()); // do |u.truncate()

        return $view;
    },
];
