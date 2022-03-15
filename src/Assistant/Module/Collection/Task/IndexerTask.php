<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Collection\Extension\Reader\ReaderFacade;
use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\InvalidMetadataException;
use Assistant\Module\Collection\Extension\Validator\ValidatorFacade;
use Assistant\Module\Collection\Extension\Writer\WriterFacade;
use Assistant\Module\Collection\Task\Indexer\IndexedDate;
use Assistant\Module\Collection\Task\Indexer\IndexingDateStrategy;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Exception\GetId3Exception;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionException;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\Track;
use BackedEnum;
use DateTime;
use Monolog\Logger;
use Psr\Container\ContainerInterface as Container;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;

/**
 * Task indeksujący utwory oraz katalogi znajdujące się w kolekcji
 */
final class IndexerTask extends AbstractTask
{
    protected static $defaultName = 'collection:index';

    private BackedEnum $indexedDateStrategy;
    private ?DateTime $fixedIndexedDate = null;

    private array $stats;

    public function __construct(
        Logger $logger,
        private ReaderFacade $reader,
        private ValidatorFacade $validator,
        private WriterFacade $writer,
        private array $parameters,
    ) {
        parent::__construct($logger);

        $this->stats = [
            'added' => [ 'file' => 0, 'dir' => 0 ],
            'invalid_metadata' => 0,
            'duplicated' => 0,
            'error' => 0,
        ];
    }

    public static function factory(Container $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(ReaderFacade::class),
            ValidatorFacade::factory($container),
            WriterFacade::factory($container),
            $container->get(Config::class)->get('collection'),
        );
    }

    protected function configure(): void
    {
        $collectionRootDir = $this->parameters['root_dir'];

        $this
            ->setDescription('Indexes tracks and directories in collection')
            ->addArgument(
                name: 'pathname',
                mode: InputArgument::OPTIONAL,
                description: 'Pathname to index',
                default: $collectionRootDir,
            )
            ->addOption(
                name: 'ensure-collection-root-dir',
                mode: InputOption::VALUE_NONE,
                description: 'Ensures that the collection root is saved in the database',
            )
            ->addOption(
                name: 'indexing-date-strategy',
                shortcut: 'i',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Specifies how to set the indexing date for tracks and directories in collection',
                default: IndexingDateStrategy::CURRENT_DATE->value
            )
            ->addOption(
                name: 'indexed-date',
                shortcut: 'd',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'The date that will be used if a fixed indexing date was selected'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $indexingDateStrategy = IndexingDateStrategy::tryFrom($input->getOption('indexing-date-strategy'));

        if (!$indexingDateStrategy) {
            $strategies = array_map(
                fn (IndexingDateStrategy $strategy) => $strategy->value,
                IndexingDateStrategy::cases()
            );

            $message = sprintf(
                'Invalid indexing date setting strategy specified. Supported strategies: %s.',
                implode(', ', $strategies)
            );

            throw new \InvalidArgumentException($message);
        }

        $this->indexedDateStrategy = $indexingDateStrategy;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($this->indexedDateStrategy === IndexingDateStrategy::FIXED_DATE) {
            $answer = $this
                ->getHelper('question')
                ->ask(
                    $input,
                    $output,
                    new Question('Please enter fixed indexed date (dd.mm.YYYY): ')
                );

            $format = 'd.m.Y';
            $dateTime = DateTime::createFromFormat($format, $answer) ?: null;

            if ($dateTime?->format($format) !== $answer) {
                throw new \InvalidArgumentException('Invalid date specified.');
            }

            $this->fixedIndexedDate = $dateTime;
        }
    }

    /** Rozpoczyna proces indeksowania kolekcji */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Task executed', self::getInputParams($input));

        $nodesToIndex = $this->getNodesToIndex(
            $input->getArgument('pathname'),
            $input->getOption('ensure-collection-root-dir')
        );

        foreach ($nodesToIndex as $node) {
            $this->logger->info('Processing node', [ 'pathname' => $node->getPathname() ]);

            try {
                $element = $this->reader->read($node);
                $this->validator->validate($element);

                $indexedDate = IndexedDate::get(
                    $element,
                    $this->indexedDateStrategy,
                    $this->fixedIndexedDate
                );

                /** @uses Track::withIndexedDate() */
                /** @uses Directory::withIndexedDate() */
                $element = $element->withIndexedDate($indexedDate);

                $this->writer->save($element);

                $this->stats['added'][$node->getType()]++;

                $this->logger->info('Node processing completed successfully');
            } catch (InvalidMetadataException) {
                $this->stats['invalid_metadata']++;

                $this->logger->warning('Track does not contains metadata');
            } catch (DuplicatedElementException $e) {
                $this->stats['duplicated']++;

                $this->logger->debug($e->getMessage());
            } catch (SimilarTracksCollectionException | GetId3Exception $e) {
                $this->stats['error']++;

                /** @uses Track::toDto() */
                /** @uses Directory::toDto() */
                $this->logger->error(
                    $e->getMessage(),
                    [ 'element' => isset($element) ? $element->toDto()->toStorage() : null ]
                );
            } catch (Throwable $e) {
                $this->stats['error']++;

                /** @uses Track::toDto() */
                /** @uses Directory::toDto() */
                $this->logger->critical($e->getMessage(), [
                    'element' => isset($element) ? $element->toDto()->toStorage() : null,
                    'stacktrace' => debug_backtrace(),
                ]);

                return self::FAILURE;
            } finally {
                unset($node, $element, $indexedDate);
            }
        }

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }

    /**
     * @param string $pathname
     * @param bool $ensureCollectionRootDir
     * @return Finder|SplFileInfo[]
     */
    private function getNodesToIndex(string $pathname, bool $ensureCollectionRootDir): array|Finder
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
