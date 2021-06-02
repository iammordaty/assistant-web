<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\BSON\Regex;
use Monolog\Logger;
use Psr\Container\ContainerInterface as Container;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task usuwający nieistniejące utwory oraz katalogu z kolekcji
 */
final class CleanerTask extends AbstractTask
{
    protected static $defaultName = 'collection:clean';

    private array $stats;

    public function __construct(
        Logger $logger,
        private DirectoryRepository $directoryRepository,
        private TrackRepository $trackRepository,
        private array $parameters,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'removed' => [ 'file' => 0, 'dir' => 0 ],
        ];
    }

    public static function factory(Container $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(DirectoryRepository::class),
            $container->get(TrackRepository::class),
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

    /**
     * Rozpoczyna proces usuwania nieistniejących elementów z kolekcji
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Task executed', self::getInputParams($input));

        $force = (bool) $input->getOption('force');
        $pathname = $input->getArgument('pathname');

        $searchCondition = [ 'pathname' => new Regex('^' . preg_quote($pathname)) ];

        $this->stats['removed']['dir'] = $this->remove(
            $this->directoryRepository,
            $searchCondition,
            $force
        );

        $this->stats['removed']['file'] = $this->remove(
            $this->trackRepository,
            $searchCondition,
            $force
        );

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /** Usuwa nieistniejące elementy z kolekcji */
    private function remove(DirectoryRepository|TrackRepository $repository, array $conditions, bool $force): int
    {
        $removed = 0;

        /** @var Directory|Track $element */
        foreach ($repository->findBy($conditions) as $element) {
            /** @uses Track::getPathname() */
            /** @uses Directory::getPathname() */
            if ($force === true || file_exists($element->getPathname()) === false) {
                $repository->delete($element);

                $removed++;
            }
        }

        return $removed;
    }
}

