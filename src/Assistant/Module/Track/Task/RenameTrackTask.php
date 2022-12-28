<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Task\CollectionGuard;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\TrackFilenameSuggestion;
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

    private array $stats;

    public function __construct(
        Logger $logger,
        private Id3Adapter $id3Adapter,
        private TrackFilenameSuggestion $trackFilenameSuggestion,
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
            $container->get(TrackFilenameSuggestion::class),
            $container->get(TrackService::class),
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
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED)
            ->addOption('target', 't', InputOption::VALUE_REQUIRED);
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

        if ($input->getOption('clean')) {
            $target = $this->trackFilenameSuggestion->getSuggestedFilename($track->getFile());
        } elseif ($format = $input->getOption('format')) {
            $metadata = $this->id3Adapter
                ->setFile($track->getFile())
                ->readId3v2Metadata();

            $metadata = array_merge(array_filter($metadata, fn ($field) => trim($field)));

            if (empty($metadata)) {
                throw new \RuntimeException('Cannot prepare target filename: no metadata');
            }

            $placeholders = array_map(fn ($placeholder) => "%$placeholder%", array_keys($metadata));
            $target = strtr($format, array_combine($placeholders, $metadata));

            if (str_contains($target, '%')) {
                preg_match_all('/%[a-z]+%/', $target, $matches);

                $message = sprintf(
                    'Cannot prepare target filename: some metadata fields are empty (%s)',
                    implode(', ', $matches[0])
                );

                throw new \RuntimeException($message);
            }

            $target .= sprintf('.%s', strtolower($track->getFile()->getExtension()));
        } elseif ($input->getOption('target')) {
            $target = $input->getOption('target');
        } else {
            // todo: niech w komunikacie będzie coś mądrzejszego, np. obsługiwane tryby działania
            throw new \RuntimeException('No option');
        }

        $target = sprintf('%s/%s', $track->getFile()->getPath(), $target);
        $target = new \SplFileInfo($target);

        if (file_exists($target->getPathname())) {
            throw new \RuntimeException("Target {$target->getPathname()} already exists!");
        }

        if (!file_exists($target->getPath()) && !mkdir($target->getPath(), 0777, true)) {
            throw new \RuntimeException("Can\'t create directory {$target->getPath()}.");
        }

        if (rename($track->getPathname(), $target->getPathname()) === false) {
            throw new \RuntimeException("Can\'t rename {$track->getPathname()} to {$target->getPathname()}.");
        }

        $this->logger->debug('Renaming track', [
            'pathname' => $track->getFile()->getBasename(),
            'target' => $target->getPathname(),
        ]);

        $this->logger->info('Task finished', $this->stats);

        return self::SUCCESS;
    }
}
