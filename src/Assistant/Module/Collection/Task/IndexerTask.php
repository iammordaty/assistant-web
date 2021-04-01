<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\EmptyMetadataException;
use Assistant\Module\Collection\Extension\Validator\ValidatorFacade;
use Assistant\Module\Collection\Extension\Writer\WriterFacade;
use Assistant\Module\Common\Extension\Backend\Exception\Exception as BackendException;
use Assistant\Module\Common\Task\AbstractTask;
use Monolog\Logger;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Task indeksujący utwory oraz katalogi znajdujące się w kolekcji
 */
final class IndexerTask extends AbstractTask
{
    private ReaderFacade $reader;

    private ValidatorFacade $validator;

    private WriterFacade $writer;

    private array $stats;

    private array $parameters;

    protected function configure(): void
    {
        $collectionRootDir = $this->app->container['parameters']['collection']['root_dir'];

        $this
            ->setName('collection:index')
            ->setDescription('Indexes tracks and directories in collection')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to index',
                $collectionRootDir
            )
            ->addOption('ensure-collection-root-dir');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->parameters = $this->app->container['parameters']['collection'];

        $this->reader = ReaderFacade::factory($this->app->container);
        $this->validator = ValidatorFacade::factory($this->app->container);
        $this->writer = WriterFacade::factory($this->app->container);

        $this->stats = [
            'added' => [ 'file' => 0, 'dir' => 0 ],
            'empty_metadata' => 0,
            'duplicated' => 0,
            'error' => 0,
        ];
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->container[Logger::class]->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $nodesToIndex = $this->getNodesToIndex(
            $input->getArgument('pathname'),
            $input->getOption('ensure-collection-root-dir')
        );

        foreach ($nodesToIndex as $node) {
            $this->app->container[Logger::class]->info('Processing node', [ 'pathname' => $node->getPathname() ]);

            try {
                $element = $this->reader->read($node);
                $this->validator->validate($element);

                $this->writer->save($element);

                $this->stats['added'][$node->getType()]++;

                $this->app->container[Logger::class]->info('Node processing completed successfully');
            } catch (EmptyMetadataException $e) {
                $this->stats['empty_metadata']++;

                $this->app->container[Logger::class]->warning('Track does not contains metadata');
            } catch (DuplicatedElementException $e) {
                $this->stats['duplicated']++;

                $this->app->container[Logger::class]->debug($e->getMessage());
            } catch (BackendException $e) {
                $this->stats['error']++;

                /** @uses Track::toDto() */
                /** @uses Directory::toDto() */
                $this->app->container[Logger::class]->error(
                    $e->getMessage(),
                    [ 'element' => isset($element) ? $element->toDto() : null ]
                );
            } catch (Throwable $e) {
                $this->stats['error']++;

                /** @uses Track::toDto() */
                /** @uses Directory::toDto() */
                $this->app->container[Logger::class]->critical($e->getMessage(), [
                    'element' => isset($element) ? $element->toDto() : null,
                    'stacktrace' => debug_backtrace(),
                ]);

                return self::FAILURE;
            } finally {
                unset($node, $element);
            }
        }

        $this->app->container[Logger::class]->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * @param string $pathname
     * @param bool $ensureCollectionRootDir
     * @return Finder|SplFileInfo[]
     */
    private function getNodesToIndex(string $pathname, bool $ensureCollectionRootDir): Finder
    {
        $finder = Finder::create([
            'pathname' => $pathname,
            'recursive' => is_dir($pathname),
            'restrict' => $this->parameters['indexed_dirs'],
            'skip_self' => false,
        ]);

        // ta flaga jest trochę słaba, bo powinno to zostać rozwiązane bardziej systemowo po stronie Findera
        // oraz listy katalogów dozwolonych / ignorowanych. Jednakże wszystkie próby dodania katalogu głównego
        // do listy indeksowanych plików sprawiały że indeksowane były także katalogi niechciane -
        // /collection/Albums, /collection/_new, /collection/Tools, itp. Być może zostanie to rozwiązane
        // po stronie biblioteki w przyszłości, bo podobne issue wiszą na githubie:
        // - https://github.com/symfony/symfony/issues/28158
        // - https://github.com/symfony/symfony/issues/34894

        if ($ensureCollectionRootDir) {
            $finder->append([ $this->parameters['root_dir'] ]);
        }

        return $finder;
    }
}
