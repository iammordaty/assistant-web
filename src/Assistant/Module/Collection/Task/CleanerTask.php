<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\BSON\Regex;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task usuwający nieistniejące utwory oraz katalogu z kolekcji
 */
final class CleanerTask extends AbstractTask
{
    private DirectoryRepository $directoryRepository;

    private TrackRepository $trackRepository;

    private array $stats;

    protected function configure(): void
    {
        $collectionRootDir = $this->app->container['parameters']['collection']['root_dir'];

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

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->directoryRepository = new DirectoryRepository($this->app->container['db']);
        $this->trackRepository = new TrackRepository($this->app->container['db']);

        $this->stats = [
            'removed' => [ 'file' => 0, 'dir' => 0 ],
        ];
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
        $this->app->container[Logger::class]->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

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

        $this->app->container[Logger::class]->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * Usuwa nieistniejące elementy z kolekcji
     *
     * @param AbstractObjectRepository $repository
     * @param array $conditions
     * @param bool $force
     * @return int
     */
    private function remove(AbstractObjectRepository $repository, array $conditions, bool $force): int
    {
        $removed = 0;

        /** @var Directory|Track $element */
        foreach ($repository->findBy($conditions) as $element) {
            if ($force === true || file_exists($element->pathname) === false) {
                $repository->remove($element);

                $removed++;
            }
        }

        return $removed;
    }
}

