<?php

use Assistant\Module\Collection\Extension\Reader\DirectoryReader;
use Assistant\Module\Collection\Extension\Reader\FileReader;
use Assistant\Module\Collection\Extension\Reader\FileReaderFacade;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\PathBreadcrumbs;
use Assistant\Module\Common\Extension\SlugifyService;
use Assistant\Module\Common\Extension\Twig\DurationTwigExtension;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\Mix\Extension\MixService;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Stats\Repository\StatsRepository;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Extension\TrackLocationArbiter;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\Client as MongoDBClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Psr\Container\ContainerInterface as Container;
use Twig\Extra\String\StringExtension;

use function DI\autowire;
use function DI\create;

return static fn (string $baseDir, array $config): array => [
    BackendClient::class => create(),

    Config::class => create()->constructor($config),

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
            'strict_variables' => false, // todo: zmienić na true
        ];

        $view = Twig::create($paths, $settings);

        $view->addExtension(DurationTwigExtension::factory());
        $view->addExtension(new TwigDebugExtension());
        $view->addExtension(new StringExtension()); // do |u.truncate()

        return $view;
    },

    Logger::class => function () use ($baseDir): LoggerInterface {
        $logsDir = $baseDir . '/app/logs';

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

    DirectoryReader::class => autowire(),

    // @todo Docelowo usunąć z DI, zastąpić serwisem dla katalogów
    DirectoryRepository::class => function (Container $container): DirectoryRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = DirectoryRepository::factory($database);

        return $repository;
    },

    Id3Adapter::class => create(),

    ReaderFacade::class => autowire(),

    TrackLocationArbiter::class => autowire(),

    TrackSearchService::class => autowire(),

    // @todo Docelowo usunąć z DI, zastąpić TrackService
    TrackRepository::class => function (Container $container): TrackRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = TrackRepository::factory($database);

        return $repository;
    },

    // @todo Docelowo usunąć z DI, zastąpić serwisem dla statystyk
    StatsRepository::class => function (Container $container): StatsRepository {
        $storage = $container->get(Config::class)->get('storage');

        $client = new MongoDBClient($storage['connection_uri']);
        $database = $client->selectDatabase($storage['database']);

        $repository = StatsRepository::factory($database);

        return $repository;
    },

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

    MixService::class => autowire(),

    PathBreadcrumbs::class => autowire(),

    // Tutaj chyba należy wprowadzić klasę faktorującą, bo obecnie
    // nie ma możliwości nadpisania parametrów poprzez formularz na stronie utworu.
    Similarity::class => function (Container $container): Similarity {
        $similarity = new Similarity(
            $container->get(TrackRepository::class),
            $container->get(Config::class)->get('similarity'),
        );

        return $similarity;
    },

    SlugifyService::class => create(),

    TrackService::class => autowire(),
];
