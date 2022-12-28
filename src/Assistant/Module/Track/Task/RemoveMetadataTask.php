<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Task\CollectionGuard;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackService;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Task usuwający metadane z podanego utworu */
final class RemoveMetadataTask extends AbstractTask
{
    protected static $defaultName = 'track:remove-metadata';

    private array $stats;

    public function __construct(
        Logger $logger,
        private Id3Adapter $id3Adapter,
        private TrackService $trackService,
    ) {
        parent::__construct($logger);

        $this->stats = [

        ];
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(Id3Adapter::class),
            $container->get(TrackService::class),
        );
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Removes metadata from track')
            ->addArgument(
                'pathname',
                InputArgument::REQUIRED,
                'Pathname to track',
            )->addOption('all', 'a', InputOption::VALUE_NONE, 'Removes all metadata fields instead of unsupported');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $pathname = $input->getArgument('pathname');

        if (!file_exists($pathname)) {
            throw new \RuntimeException("Target {$pathname} does not exists");
        }

        $track = $this->trackService->createFromFile($pathname);

        $guard = new CollectionGuard($this->trackService, $this->getHelper('question'), $input, $output);
        $guard($track);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Task executed', self::getInputParams($input));

        $pathname = $input->getArgument('pathname');
        $track = $this->trackService->createFromFile($pathname);

        $this->id3Adapter
            ->setFile($track->getFile())
            ->setId3WriterOptions([
                'tag_encoding' => 'UTF-8',
                'tagformats' => [ 'id3v2.3' ],
                'remove_other_tags' => true,
            ]);

        $removeAllMetadata = $input->getOption('all');
        $metadata = $removeAllMetadata ? [] : $this->id3Adapter->readId3v2Metadata();

        $this->id3Adapter->writeId3v2Metadata($metadata, true);

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }
}
