<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\BSON\Regex;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task usuwający nieistniejące utwory oraz katalogu z kolekcji
 */
class CleanerTask extends AbstractTask
{
    /**
     * Tablica asocjacyjna zawierająca statystyki zadania
     *
     * @var array
     */
    private $stats;

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
                InputArgument::OPTIONAL,
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
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->app->log->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $force = (bool) $input->getOption('force');

        $pathname = $input->getArgument('pathname');
        $relativePathname = str_replace($this->parameters['root_dir'], '', $pathname);
        $searchCondition = [ 'pathname' => new Regex('^' . preg_quote($relativePathname)) ];

        $this->stats['removed']['dir'] = $this->remove(
            (new DirectoryRepository($this->app->container['db'])),
            $searchCondition,
            $force
        );

        $this->stats['removed']['file'] = $this->remove(
            (new TrackRepository($this->app->container['db'])),
            $searchCondition,
            $force
        );

        $this->app->log->info('Task finished', $this->stats);

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

        return $removed;
    }
}
