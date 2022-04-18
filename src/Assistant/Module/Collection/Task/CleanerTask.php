<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Storage\Regex;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Search\Extension\DirectorySearchService;
use Assistant\Module\Search\Extension\SearchCriteria;
use Assistant\Module\Search\Extension\SearchCriteriaFacade;
use Assistant\Module\Search\Extension\TrackSearchService;
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
        private DirectorySearchService $directorySearchService,
        private DirectoryService $directoryService,
        private TrackSearchService $trackSearchService,
        private TrackService $trackService,
        private array $parameters,
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
            $container->get(DirectorySearchService::class),
            $container->get(DirectoryService::class),
            $container->get(TrackSearchService::class),
            $container->get(TrackService::class),
            $container->get(Config::class)->get('collection'),
        );
    }

    protected function configure(): void
    {
        $collectionRootDir = $this->parameters['root_dir'];

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
        $this->logger->info('Task executed', self::getInputParams($input));

        $force = (bool) $input->getOption('force');
        $pathname = $input->getArgument('pathname');

        $regex = Regex::startsWith($pathname);
        $searchCriteria = SearchCriteriaFacade::createFromPathname($regex);

        $this->stats['removed']['file'] = $this->removeTracks($searchCriteria, $force);
        $this->stats['removed']['dir'] = $this->removeDirectories($searchCriteria, $force);

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /** Usuwa nieistniejące utworzy muzyczne */
    private function removeTracks(SearchCriteria $searchCriteria, bool $force): int
    {
        $removed = 0;

        foreach ($this->trackSearchService->findBy($searchCriteria) as $track) {
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

        /** @var Directory $directory */
        foreach ($this->directorySearchService->findBy($searchCriteria) as $directory) {
            /** @uses Directory::getPathname() */
            if ($force || !$directory->getFile()->isReadable()) {
                $this->directoryService->remove($directory);

                $removed++;
            }
        }

        return $removed;
    }
}
