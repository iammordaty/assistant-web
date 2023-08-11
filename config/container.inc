<?php

use Assistant\Module\Collection\Extension\Autocomplete\TrackAutocompleteService;
use Assistant\Module\Collection\Extension\Reader\DirectoryReader;
use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Collection\Extension\Reader\IncomingFileReader;
use Assistant\Module\Collection\Extension\Reader\MetadataParser\MetadataParser;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumbs;
use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\LogView;
use Assistant\Module\Common\Extension\Pagerfanta\PagerfantaTwigRuntimeLoaderFactory;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\Twig\DurationTwigExtension;
use Assistant\Module\Common\Extension\Twig\IntlMessageFormatterTwigExtension;
use Assistant\Module\Common\Repository\MetadataFieldAutocompleteRepository;
use Assistant\Module\Common\Repository\LogRepository;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Mix\Extension\MixService;
use Assistant\Module\Search\Extension\DirectorySearchService;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\Similarity\Similarity;
use Assistant\Module\Track\Extension\Similarity\SimilarityBuilder;
use Assistant\Module\Track\Extension\TrackFilenameSuggestion;
use Assistant\Module\Track\Extension\TrackLocationArbiter;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Repository\TrackRepository;
use Assistant\Module\Track\Repository\TrackStatsRepository;
use MongoDB\Client as MongoDBClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\MongoDBHandler;
use Monolog\Logger;
use Pagerfanta\Twig\Extension\PagerfantaExtension;
use Psr\Container\ContainerInterface as Container;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Extra\String\StringExtension;

use function DI\autowire;
use function DI\create;

return static fn (string $baseDir, array $config): array => [
    MetadataFieldAutocompleteRepository::class => function (Container $container): MetadataFieldAutocompleteRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = MetadataFieldAutocompleteRepository::factory($database);

        return $repository;
    },

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

    IncomingFileReader::class => function (Container $container): IncomingFileReader {
        $metadataParserParams = $container->get(Config::class)->get('track_metadata_parser');

        $fileReader = new IncomingFileReader(
            $container->get(Id3Adapter::class),
            new MetadataParser($metadataParserParams),
            $container->get(SlugifyService::class),
        );

        return $fileReader;
    },

    Id3Adapter::class => create(),

    Logger::class => function (Container $container) use ($baseDir): LoggerInterface {
        $storage = $container->get(Config::class)->get('storage');

        $handlers = [
            new MongoDBHandler(
                new MongoDBClient($storage['connection_uri']),
                $storage['database'],
                LogRepository::COLLECTION_NAME
            ),
            (new ErrorLogHandler())->setFormatter(new LineFormatter(
                '[%datetime%] [%level_name%] %message% %context%',
                'd.m.Y, H:i:s',
                false,
                true
            )),
        ];

        return new Logger('assistant-logger', $handlers);
    },

    LogRepository::class => function (Container $container): LogRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = LogRepository::factory($database);

        return $repository;
    },

    LogView::class => autowire(),

    MixService::class => autowire(),

    Breadcrumbs::class => autowire(),

    BreadcrumbsBuilder::class => autowire(),

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

    TrackFilenameSuggestion::class => autowire(),

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

    Twig::class => function (Container $container) use ($baseDir): Twig {
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
        $view->addExtension(new IntlMessageFormatterTwigExtension());
        $view->addExtension(new PagerfantaExtension());
        $view->addExtension(new StringExtension()); // do |u.truncate()
        $view->addExtension(new TwigDebugExtension());

        $pagerfantaRuntimeLoader = PagerfantaTwigRuntimeLoaderFactory::createRuntimeLoader($container);
        $view->addRuntimeLoader($pagerfantaRuntimeLoader);

        return $view;
    },
];
