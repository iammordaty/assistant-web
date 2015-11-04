<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task reindeksujący utwory znajdujące się w kolekcji
 */
class ReindexerTask extends AbstractTask
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
            ->setName('collection:reindex')
            ->setDescription(
                'Reindexes tracks and directories in collection (shortcut for collection:clean -f && collection:index)'
            );
    }

    /**
     * Rozpoczyna proces reindeksowania kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->info('Uruchamianie zadania czyszczenia kolekcji...');

        (new CleanerTask($this->app))->run(
            new ArrayInput([ '--force' => true ]),
            $output
        );

        $this->info('');
        $this->info('Uruchamianie indeksowania kolekcji...');

        (new IndexerTask($this->app))->run(
            new ArrayInput([ ]),
            $output
        );

        unset($input);
    }
}
