<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\Collection;
use Assistant\Module\Track;
use Assistant\Module\Directory;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Task indeksujący utwory znajdujące się w kolekcji
 */
class IndexerTask extends AbstractTask
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
        $this->parameters = $this->app->container->parameters['collection']['indexer'];
        
        $this
            ->setName('collection:index')
            ->setDescription('Indeksuje utwory oraz katalogi znajdujące się w kolekcji')
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear collection before indexing');
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $processor = new Collection\Extension\Processor\Processor($this->app->container->parameters);
        $writer = new Collection\Extension\Writer\Writer($this->app->container['db']);

        if ($input->getOption('clear') === true) {
            $writer->clear();
        }

        $stats = [ 'added' => [ 'file' => 0, 'dir' => 0 ], 'empty' => 0, 'duplicated' => 0, 'error' => 0, ];

        /* @var $node \Assistant\Module\File\Extension\SplFileInfo */
        foreach ($this->getIterator() as $node) {
            try {
                $element = $processor->process($node);
                $writer->save($element);

                $stats['added'][$node->getType()]++;

                $this->info('.', false);
            } catch (Collection\Extension\Processor\Exception\EmptyMetadataException $e) {
                $stats['empty']++;

                $this->error('.', false);
            } catch (Collection\Extension\Writer\Exception\DuplicatedElementException $e) {
                if ($node->isDot() === false) {
                    $stats['duplicated']++;

                    $this->comment('.', false);
                }
            } catch (\Exception $e) {
                $stats['error']++;

                $this->error($e->getMessage());
            } finally {
                unset($node, $element);
            }
        }

        $this->showSummary($stats);

        unset($input, $output);
    }

    /**
     * @return \IgnoredPathIterator
     */
    private function getIterator()
    {
        return new IgnoredPathIterator(
            new PathFilterIterator(
                new RecursiveDirectoryIterator($this->parameters['root_dir']),
                $this->parameters['root_dir'],
                $this->parameters['excluded_dirs']
            ),
            $this->parameters['ignored_dirs'],
            IgnoredPathIterator::SELF_FIRST,
            IgnoredPathIterator::CATCH_GET_CHILD
        );
    }

    /**
     * Wyświetla podsumowanie procesu indeksowania
     *
     * @param array $stats
     */
    private function showSummary(array $stats)
    {
        $this->info('');
        $this->info('Zakończono.');
        $this->info('');

        $this->info(
            sprintf(
                'Liczba dodanych elementów: %d (plików: %d, katalogów: %d)',
                $stats['added']['file'] + $stats['added']['dir'],
                $stats['added']['file'],
                $stats['added']['dir']
            )
        );
        $this->info(sprintf('Liczba utworów bez metadanych: %d', $stats['empty']));
        $this->info(sprintf('Liczba pominiętych utworów: %d', $stats['duplicated']));
        $this->info(sprintf('Liczba elementów nie dodanych z powodu błędu: %d', $stats['error']));

        $this->info('');
        $this->info(sprintf('Maksymalne użycie pamięci: %.3f MB', (memory_get_peak_usage() / (1024 * 1024))));
    }
}
