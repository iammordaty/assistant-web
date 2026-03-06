<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\Service\DirectorySearchService;
use Assistant\Module\Search\Extension\Service\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Task usuwający nieistniejące utwory oraz katalogu z kolekcji */
final class CleanerTask extends AbstractTask
{
    protected static $defaultName = 'collection:clean';

    private array $stats;

    public function __construct(
        Logger $logger,
        private Config $config,
        private DirectorySearchService $directorySearchService,
        private DirectoryService $directoryService,
        private TrackSearchService $trackSearchService,
        private TrackService $trackService,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'removed' => [ 'file' => 0, 'dir' => 0 ],
        ];
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(Config::class),
            $container->get(DirectorySearchService::class),
            $container->get(DirectoryService::class),
            $container->get(TrackSearchService::class),
            $container->get(TrackService::class),
        );
    }

    protected function configure(): void
    {
        $collectionRootDir = $this->config->get('collection.root_dir');

        $this
            ->setName('collection:clean')
            ->setDescription('Removes non-existent tracks and directories from collection')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to remove from collection',
                $collectionRootDir
            )->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not check file existence');
    }

    /** Rozpoczyna proces usuwania nieistniejących elementów z kolekcji */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $force = (bool) $input->getOption('force');
        $pathname = $input->getArgument('pathname');

        $regex = Regex::startsWith($pathname);
        $searchCriteria = SearchCriteriaFacade::createFromPathname($regex);

        $this->stats['removed']['file'] = $this->removeTracks($searchCriteria, $force);
        $this->stats['removed']['dir'] = $this->removeDirectories($searchCriteria, $force);

        $this->logger->debug('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /** Usuwa nieistniejące utworzy muzyczne */
    private function removeTracks(SearchCriteria $searchCriteria, bool $force): int
    {
        $removed = 0;

        $trackSearchResult = $this->trackSearchService->search($searchCriteria);

        foreach ($trackSearchResult->tracks as $track) {
            if ($force || !$track->getFile()->isReadable()) {
                $this->trackService->remove($track);

                $removed++;
            }
        }

        return $removed;
    }

    /** Usuwa nieistniejące elementy z kolekcji */
    private function removeDirectories(SearchCriteria $searchCriteria, bool $force): int
    {
        $removed = 0;

        foreach ($this->directorySearchService->search($searchCriteria) as $directory) {
            if ($force || !$directory->getFile()->isReadable()) {
                $this->directoryService->remove($directory);

                $removed++;
            }
        }

        return $removed;
    }
}
