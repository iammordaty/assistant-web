<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Task\CollectionGuard;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackRenameService;
use Assistant\Module\Track\Extension\TrackService;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RenameTrackTask extends AbstractTask
{
    protected static $defaultName = 'track:rename';

    public function __construct(
        Logger $logger,
        private TrackService $trackService,
        private TrackRenameService $trackRenameService,
        private Id3Adapter $id3Adapter,
    ) {
        parent::__construct($logger);
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(Logger::class),
            $container->get(TrackService::class),
            $container->get(TrackRenameService::class),
            $container->get(Id3Adapter::class),
        );
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Renames the file of the specified track')
            ->addArgument(
                'pathname',
                InputArgument::REQUIRED,
                'Pathname to track',
            )
            ->addOption('clean', 'c', InputOption::VALUE_NONE)
            ->addOption('mark-as-ready', 'r', InputOption::VALUE_NONE)
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED)
            ->addOption('target', 't', InputOption::VALUE_REQUIRED);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $pathname = $input->getArgument('pathname');

        if (!file_exists($pathname)) {
            throw new \RuntimeException("File {$pathname} does not exists");
        }

        $track = $this->trackService->createFromFile($pathname);

        $guard = new CollectionGuard($this->trackService, $this->getHelper('question'), $input, $output);
        $guard($track);

        if ($this->trackService->getLocationArbiter()->isInCollection($track) && $input->getOption('mark-as-ready')) {
            throw new \RuntimeException("File {$pathname} is in collection so it cannot be marked as ready.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $pathname = $input->getArgument('pathname');
        $track = $this->trackService->createFromFile($pathname);

        if ($input->getOption('clean')) {
            $result = $this->trackRenameService->clean($track);
        } elseif ($format = $input->getOption('format')) {
            // dla CLI źródłem prawdy dla nazwy są tagi zapisane w pliku - czytamy je tutaj
            // i przekazujemy do rename() (rename nie analizuje już pliku samodzielnie, patrz F4)
            $metadata = $this->id3Adapter
                ->setFile($track->getFile())
                ->analyze()
                ->getMetadata();

            $result = $this->trackRenameService->rename($track, $format, $metadata, $input->getOption('mark-as-ready'));
        } elseif ($targetString = $input->getOption('target')) {
            $result = $this->trackRenameService->target($track, $targetString);
        } else {
            // todo: niech w komunikacie będzie coś mądrzejszego, np. obsługiwane tryby działania
            throw new \RuntimeException('No option');
        }

        $this->logger->info('Successfully renamed track', [
            'pathname' => $result->file->getPathname(),
            'target' => $result->file->getPathname()
        ]);

        $this->logger->debug('Task finished');

        return self::SUCCESS;
    }
}
