<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Collection;

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
        $this->parameters = $this->app->container->parameters['collection'];

        $this
            ->setName('collection:index')
            ->setDescription('Indexes tracks and directories in collection')
            ->addArgument(
                'pathname',
                InputOption::VALUE_REQUIRED,
                'Pathname to index',
                $this->parameters['root_dir']
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->stats = [
            'added' => [ 'file' => 0, 'dir' => 0 ],
            'empty_metadata' => 0,
            'duplicated' => 0,
            'error' => 0,
        ];
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $processor = new Collection\Extension\Processor\Processor($this->app->container->parameters);
        $writer = new Collection\Extension\Writer\Writer($this->app->container['db']);

        foreach ($this->getIterator($input->getArgument('pathname')) as $node) {
            try {
                $element = $processor->process($node);
                $writer->save($element);

                $this->stats['added'][$node->getType()]++;

                $this->info('.', false);
            } catch (Collection\Extension\Processor\Exception\EmptyMetadataException $e) {
                $this->stats['empty_metadata']++;

                $this->error('.', false);
            } catch (Collection\Extension\Writer\Exception\DuplicatedElementException $e) {
                if ($node->isDot() === false) {
                    $this->stats['duplicated']++;

                    $this->comment('.', false);
                }
            } catch (\Exception $e) {
                $this->stats['error']++;

                $this->error($e->getMessage());
            } finally {
                unset($node, $element);
            }
        }

        $this->showSummary();

        unset($input, $output);
    }

    /**
     * @param string $pathname
     * @return IgnoredPathIterator
     */
    private function getIterator($pathname)
    {
        if (is_file($pathname)) {
            $relativePathname = str_replace(sprintf('%s/', $this->parameters['root_dir']), '', $pathname);

            $iterator = new \RecursiveArrayIterator(
                [ new SplFileInfo($pathname, $relativePathname) ]
            );
        } else {
            $iterator = new RecursiveDirectoryIterator($pathname);
        }

        return new IgnoredPathIterator(
            new PathFilterIterator(
                $iterator,
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
     */
    private function showSummary()
    {
        $this->info('');
        $this->info('Zakończono.');
        $this->info('');

        $this->info(
            sprintf(
                'Liczba dodanych elementów: %d (plików: %d, katalogów: %d)',
                $this->stats['added']['file'] + $this->stats['added']['dir'],
                $this->stats['added']['file'],
                $this->stats['added']['dir']
            )
        );
        $this->info(sprintf('Liczba utworów bez metadanych: %d', $this->stats['empty_metadata']));
        $this->info(sprintf('Liczba pominiętych utworów: %d', $this->stats['duplicated']));
        $this->info(sprintf('Liczba elementów nie dodanych z powodu błędu: %d', $this->stats['error']));

        $this->info('');
        $this->info(sprintf('Maksymalne użycie pamięci: %.3f MB', (memory_get_peak_usage() / (1024 * 1024))));
    }
}
