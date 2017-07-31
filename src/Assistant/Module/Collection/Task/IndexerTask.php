<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Collection;
use Assistant\Module\Common;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task indeksujący utwory znajdujące się w kolekcji
 */
class IndexerTask extends AbstractTask
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
            ->setName('collection:index')
            ->setDescription('Indexes tracks and directories in collection')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
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
        $this->app->log->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $processor = new Collection\Extension\Processor\Processor($this->app->container->parameters);
        $writer = new Collection\Extension\Writer\Writer($this->app->container['db']);

        foreach ($this->getIterator($input->getArgument('pathname')) as $node) {
            $this->app->log->info('Processing node', [ 'pathname' => $node->getPathname() ]);

            try {
                $element = $processor->process($node);
                $writer->save($element);

                $this->stats['added'][$node->getType()]++;

                $this->app->log->info('Node processing completed successfully');
            } catch (Collection\Extension\Processor\Exception\EmptyMetadataException $e) {
                $this->stats['empty_metadata']++;

                $this->error('.', false);
            } catch (Collection\Extension\Writer\Exception\DuplicatedElementException $e) {
                if ($node->isDot() === false) {
                    $this->stats['duplicated']++;
                }

                // it's not a big deal, so log as debug
                $this->app->log->debug($e->getMessage());
            } catch (Common\Extension\Backend\Exception\Exception $e) {
                $this->stats['error']++;

                $this->app->log->error(
                    $e->getMessage(),
                    [ 'element' => isset($element) ? $element->toArray() : null ]
                );
            } catch (\Exception $e) {
                $this->stats['error']++;

                $this->app->log->error($e->getMessage());
            } finally {
                unset($node, $element);
            }
        }

        $this->app->log->info('Task finished', $this->stats);

        unset($input, $output, $processor, $writer);
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
}
