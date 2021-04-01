<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;

use Monolog\Logger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task reindeksujący utwory znajdujące się w kolekcji
 */
class ReindexerTask extends AbstractTask
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('collection:reindex')
            ->setDescription(
                'Reindexes tracks and directories in collection (shortcut for collection:clean -f && collection:index)'
            );
    }

    /**
     * Rozpoczyna proces reindeksowania kolekcji
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->container[Logger::class]->info('Task executed');

        $this->app->container[Logger::class]->info('Executing "collection:clean -f"');

        (new CleanerTask($this->app))->run(
            new ArrayInput([ '--force' => true ]),
            $output
        );

        $this->app->container[Logger::class]->info('Executing "collection:index"');

        (new IndexerTask($this->app))->run(
            new ArrayInput([ ]),
            $output
        );

        $this->app->container[Logger::class]->info('Task finished');

        return self::SUCCESS;
    }
}
