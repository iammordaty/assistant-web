<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use DateTime;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SimilarTracksCollectionReIndexerTask extends AbstractTask
{
    protected static $defaultName = 'collection:reindex-similar-tracks';

    public function __construct(
        Logger $logger,
        private Config $config,
        private SimilarTracksCollectionService $similarTracksCollectionService,
        private TrackSearchService $searchService,
    ) {
        parent::__construct($logger);
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(Config::class),
            $container->get(SimilarTracksCollectionService::class),
            $container->get(TrackSearchService::class),
        );
    }

    protected function configure(): void
    {
        $this->setDescription('Re-indexes tracks in similar tracks collection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $backupSuffix = sprintf('.%s.bak', (new DateTime())->format('y.m.d'));

        $dir = $this->config->get('collection.metadata_dirs.music_similarity');
        $collectionPathname = sprintf('%s/%s', $dir, $this->similarTracksCollectionService->getCollectionPathname());

        if (file_exists($collectionPathname)) {
            rename($collectionPathname, $collectionPathname . $backupSuffix);
        }

        $jukeboxPathname = $this->similarTracksCollectionService->getJukeboxPathname();

        if ($jukeboxPathname && file_exists($jukeboxPathname)) {
            rename($jukeboxPathname, $jukeboxPathname . $backupSuffix);
        }

        $this->similarTracksCollectionService->initializeCollection();

        foreach ($this->config->get('collection.indexed_dirs') as $dir) {
            $this->logger->debug('Re-indexing directory', [ 'dir' => $dir ]);

            $this->similarTracksCollectionService->add(new SplFileInfo($dir));
        }

        $this->logger->debug('Generating jukebox file...',);

        // wygeneruj nowy plik jukebox dla utworzonej kolekcji
        $track = $this->searchService->findOne(new SearchCriteria());
        $this->similarTracksCollectionService->getSimilarTracks($track->getFile());

        $this->logger->debug('Task finished');

        return self::SUCCESS;
    }
}
