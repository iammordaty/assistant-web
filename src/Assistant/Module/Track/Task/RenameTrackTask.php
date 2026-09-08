<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Collection\Task\CollectionGuard;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\Track\Extension\DateDirectory;
use Assistant\Module\Track\Extension\TrackRenameService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SplFileInfo;

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
            ->addOption('target', 't', InputOption::VALUE_REQUIRED)
            ->addOption(
                'date-dir',
                'D',
                InputOption::VALUE_NONE,
                'Prefixes the target with a <year>/<NN. month> directory',
            )
            ->addOption(
                'date-dir-year',
                null,
                InputOption::VALUE_REQUIRED,
                'Explicit year to use instead of the one derived from the file',
            )
            ->addOption(
                'date-dir-month',
                null,
                InputOption::VALUE_REQUIRED,
                'Explicit "NN. month" to use instead of the one derived from the file',
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $pathname = $input->getArgument('pathname');

        if (!file_exists($pathname)) {
            throw new \RuntimeException("File {$pathname} does not exists");
        }

        $track = $this->trackService->createFromFile($pathname);

        // pytanie o zgodę ma sens tylko tu; brak potwierdzenia kończy się wyjątkiem, a uruchomienie
        // nieinteraktywne w ogóle nie wchodzi do interact() - dlatego samą regułę egzekwuje execute()
        $guard = new CollectionGuard($this->trackService, $this->getHelper('question'), $input, $output);
        $guard($track);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('Task executed', self::getInputParams($input));

        $pathname = $input->getArgument('pathname');
        $track = $this->trackService->createFromFile($pathname);

        $this->assertOptionsAllowed($input, $track);

        if ($input->getOption('clean')) {
            $result = $this->trackRenameService->clean($track);
        } elseif ($format = $input->getOption('format')) {
            // dla CLI źródłem prawdy dla nazwy są tagi zapisane w pliku - czytamy je tutaj
            // i przekazujemy do rename() (rename nie analizuje już pliku samodzielnie, patrz F4)
            $metadata = $this->id3Adapter
                ->setFile($track->getFile())
                ->analyze()
                ->getMetadata();

            $format = $this->prependDateDir($input, $format, $track->getFile());

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

    /**
     * Reguły dopuszczalności operacji. Celowo w execute(), a nie w interact(): Symfony wywołuje
     * interact() wyłącznie dla wejścia interaktywnego, więc strażnik postawiony tam nie działa
     * przy uruchomieniu w procesie (ConsoleCommandRunner::runSync ustawia non-interactive).
     */
    private function assertOptionsAllowed(InputInterface $input, Track|IncomingTrack $track): void
    {
        if (!$this->trackService->getLocationArbiter()->isInCollection($track)) {
            return;
        }

        $pathname = $track->getFile()->getPathname();

        // Zmiana nazwy pliku w kolekcji wymaga świadomego potwierdzenia (CollectionGuard w interact()).
        // Symfony pomija interact() dla wejścia nieinteraktywnego - a tak działa uruchomienie
        // w procesie (ConsoleCommandRunner::runSync, m.in. zbiorcza zmiana nazwy z listy incoming) -
        // więc bez tego warunku operacja przeszłaby w ogóle bez pytania.
        if (!$input->isInteractive()) {
            throw new \RuntimeException(
                "File {$pathname} is in collection so it can only be renamed interactively."
            );
        }

        if ($input->getOption('mark-as-ready')) {
            throw new \RuntimeException("File {$pathname} is in collection so it cannot be marked as ready.");
        }

        // rok i miesiąc utworu w kolekcji wynikają z jego położenia i są nienaruszalne;
        // doklejenie segmentu daty utworzyłoby zagnieżdżony, fałszywy katalog rok/miesiąc
        if ($input->getOption('date-dir')) {
            throw new \RuntimeException("File {$pathname} is in collection so its date directory cannot be changed.");
        }
    }

    /**
     * Dokłada do formatu segment <rok>/<NN. miesiąc>, gdy poproszono o uporządkowanie plików
     * wg daty. Segment jest częścią formatu, więc odbudowuje katalogi tak samo jak każdy inny
     * poziom struktury. Rok i miesiąc są niezależne - pominięty bierze się z daty pliku.
     */
    private function prependDateDir(InputInterface $input, string $format, SplFileInfo $file): string
    {
        if (!$input->getOption('date-dir')) {
            return $format;
        }

        $dateDir = DateDirectory::forFile(
            $file,
            DateDirectory::tryParseYear($input->getOption('date-dir-year')),
            DateDirectory::tryParseMonth($input->getOption('date-dir-month')),
        );

        return sprintf('%s/%s', $dateDir, $format);
    }
}
