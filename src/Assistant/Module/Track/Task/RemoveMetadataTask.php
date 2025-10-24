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
            )->addOption(
                'keep-supported-fields',
                'k',
                InputOption::VALUE_NONE,
                'Keeps supported metadata fields instead of removing them',
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
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
        $this->logger->debug('Task executed', self::getInputParams($input));

        $pathname = $input->getArgument('pathname');
        $track = $this->trackService->createFromFile($pathname);

        $this
            ->id3Adapter
            ->setFile($track->getFile());

        $keepSupportedFields = $input->getOption('keep-supported');

        $metadata = $keepSupportedFields
            ? $this->id3Adapter->analyze()->getMetadata()
            : [];

        $this->id3Adapter->writeMetadata($metadata);

        $this->logger->debug('Task finished', $this->stats);

        return self::SUCCESS;
    }
}
