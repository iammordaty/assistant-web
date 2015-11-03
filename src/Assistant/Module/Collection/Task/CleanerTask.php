<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Repository\TrackRepository;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Task usuwający nieistniejące utwory oraz katalogu z kolekcji
 */
class CleanerTask extends AbstractTask
{
    /**
     * @var array
     */
    private $parameters;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->parameters = $this->app->container->parameters['collection'];

        $this
            ->setName('collection:clean')
            ->setDescription('Removes non-existent tracks and directories from collection')
            ->addArgument(
                'pathname',
                InputOption::VALUE_REQUIRED,
                'Pathname to remove from collection',
                $this->parameters['root_dir']
            )->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not check file existence');
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->stats = [
            'removed' => [ 'file' => 0, 'dir' => 0 ],
        ];
    }

    /**
     * Rozpoczyna proces usuwania nieistniejących elementów z kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pathname = $input->getArgument('pathname');
        $searchCondition = $pathname === $this->parameters['root_dir']
            ? [ ]
            : [ 'pathname' => new \MongoRegex(sprintf('/^%s/', $pathname)) ];

        $force = (bool) $input->getOption('force');

        if (is_dir($pathname)) {
            $this->stats['removed']['dir'] = $this->remove(
                (new DirectoryRepository($this->app->container['db'])),
                $searchCondition,
                $force
            );
        }

        $this->stats['removed']['file'] = $this->remove(
            (new TrackRepository($this->app->container['db'])),
            $searchCondition,
            $force
        );

        $this->showSummary();

        unset($searchCondition, $input, $output);
    }

    /**
     * Usuwa nieistniejące elementy z kolekcji
     *
     * @param AbstractObjectRepository $repository
     * @param array $conditions
     * @param bool $force
     * @return int
     */
    private function remove(AbstractObjectRepository $repository, array $conditions, $force)
    {
        $removed = 0;

        foreach ($repository->findBy($conditions) as $element) {
            $pathname = sprintf('%s%s', $this->parameters['root_dir'], $element->pathname);

            if ($force === true || file_exists($pathname) === false) {
                $repository->remove($element);

                $removed++;
            }
        }

        unset($repository, $conditions);

        return $removed;
    }

    /**
     * Wyświetla podsumowanie procesu indeksowania
     */
    private function showSummary()
    {
        $this->info('');
        $this->info('Zakończono.');
        $this->info('');

        $this->info(
            sprintf(
                'Liczba usuniętych elementów: %d (plików: %d, katalogów: %d)',
                $this->stats['removed']['file'] + $this->stats['removed']['dir'],
                $this->stats['removed']['file'],
                $this->stats['removed']['dir']
            )
        );

        $this->info('');
        $this->info(sprintf('Maksymalne użycie pamięci: %.3f MB', (memory_get_peak_usage() / (1024 * 1024))));
    }
}
