<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;
use Assistant\Module\Common\Extension\Breadcrumbs\BreadcrumbsBuilder;
use Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator\EmptyRouteGenerator;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Monolog\Logger;
use SplFileInfo;

// wyciągnięcie logiki z RenameTrackTask na szybko, totalne yolo, bo o co innego chodziło, nie przywiązywać się;
// wypadałoby to przy którejś okazji ogarnąć zgodnie ze sztuką :-)
final class TrackRenameService
{
    private array $logContext = [];

    private array $leftoverPaths = [];
    private array $createdPaths = [];

    public function __construct(
        private BreadcrumbsBuilder $breadcrumbsBuilder,
        private Config $config,
        private Id3Adapter $id3Adapter,
        private Logger $logger,
        private TrackFilenameSuggestion $trackFilenameSuggestion,
        private TrackService $trackService,
    ) {
    }

    public function clean(Track|IncomingTrack $track): SplFileInfo
    {
        $target = $this->trackFilenameSuggestion->getSuggestedFilename($track->getFile());

        return $this->move($track->getFile(), new SplFileInfo($target));
    }

    public function rename(Track|IncomingTrack $track, string $format, bool $markAsReady): SplFileInfo
    {
        // do innej klasy

        $metadata = $this->id3Adapter
            ->setFile($track->getFile())
            ->analyze()
            ->getMetadata();

        $metadata = array_merge(array_filter($metadata, fn ($field) => trim($field)));

        if (empty($metadata)) {
            throw new \RuntimeException('Cannot prepare target filename: no metadata');
        }

        $metadata = array_map(function ($field) {
            if (is_numeric($field)) {
                return $field;
            }

            $field = str_replace([ '/', ':' ], '-', $field);
            $field = str_replace('"', '\'', $field);
            $field = str_replace([ '*', '?' ], '', $field);

            return $field;
        }, $metadata);

        if (isset($metadata['track_number']) && $metadata['track_number'] < 10) {
            $metadata['track_number'] = '0' . $metadata['track_number'];
        }

        $placeholders = array_map(fn ($placeholder) => "%{$placeholder}%", array_keys($metadata));
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

        if ($markAsReady) {
            $target = sprintf('%s/%s', basename($this->config->get('collection.ready_dir')), $target);
        }

        return $this->move($track->getFile(), new SplFileInfo($target));
    }

    public function target(Track|IncomingTrack $track, SplFileInfo $target): SplFileInfo
    {
        return $this->move($track->getFile(), $target);
    }

    public function getLeftoverPaths(): array
    {
        return $this->leftoverPaths;
    }

    public function getCreatedPaths(): array
    {
        return $this->createdPaths;
    }

    private function move(SplFileInfo $source, SplFileInfo $target): SplFileInfo
    {
        $this->leftoverPaths = [];
        $this->createdPaths = [];

        // do location arbitra
        $isSingle = str_contains($source->getPathname(), '/collection/Singles');

        $this->logContext['source'] = $source->getPathname();
        $this->logContext['is_single'] = $isSingle;

        if ($isSingle) {
            $baseDir = dirname($source->getPath(), 2);
        } else {
            $baseDir = $source->getPath();
        }

        $target = sprintf('%s/%s', $baseDir, $target);
        $target = new SplFileInfo($target);

        if (file_exists($target->getPathname())) {
            throw new \RuntimeException("Target {$target->getPathname()} already exists!");
        }

        $modificationTime = $source->getMTime();
        $this->createdPaths = $this->calculateNonExistentPaths($target);

        $this->logContext['created_paths'] = $this->createdPaths;

        if (!file_exists($target->getPath()) && !mkdir($target->getPath(), 0777, true)) {
            throw new \RuntimeException("Can\'t create directory {$target->getPath()}.");
        }

        $this->logContext['target'] = $target->getPathname();
        $this->logContext['pathname'] = $target->getPathname();

        if (rename($source->getPathname(), $target->getPathname()) === false) {
            throw new \RuntimeException("Can\'t rename {$source->getPathname()} to {$target->getPathname()}.");
        }

        foreach ($this->createdPaths as $path) {
            touch($path, $modificationTime, $modificationTime);
        }

        if ($isSingle && $this->trackService->getLocationArbiter()->isInCollection($source)) {
            $this->leftoverPaths = $this->calculateLeftoverPaths($source);

            $this->logContext['leftover_paths'] = $this->leftoverPaths;

            foreach ($this->leftoverPaths as $leftoverPath) {
                rmdir($leftoverPath);
            }
        }

        $this->logger->debug('Renamed track', $this->logContext);

        return $target;
    }

    private function calculateNonExistentPaths(SplFileInfo $target): array
    {
        $breadcrumbs = $this->breadcrumbsBuilder
            ->withPath($target->getPath())
            ->withRouteGenerator(new EmptyRouteGenerator())
            ->createBreadcrumbs();

        $paths = array_map(fn (Breadcrumb $breadcrumb) => $breadcrumb->pathname, $breadcrumbs);
        $paths = array_filter($paths, fn (string $pathname) => !file_exists($pathname));

        return [ ...$paths ];
    }

    private function calculateLeftoverPaths(SplFileInfo $file): array
    {
        // może da się to ograć bardziej elegancko w innym miejscu, ale tutaj chodzi o to, że jeżeli zmieniamy
        // nazwę artysty lub albumu to przenosimy plik do innego katalogu, np.
        // z /collection/Singles/xxx/X and Y/Z/X and Y - Z.mp3
        // na /collection/Singles/xxx/X feat. Y/Z (Extended Mix)/X feat. Y - Z (Extended Mix).mp3
        // to zostawiane są puste foldery, które tutaj usuwamy
        // ewentualny kontekst: \Assistant\Module\Track\Controller\Track\EditController::rename

        $isPathEmpty = fn (string $path): bool => count(scandir($path)) == 2; // dwa bo scan dir liczy . i ..

        $breadcrumbs = $this->breadcrumbsBuilder
            ->withPath($file->getPath())
            ->withRouteGenerator(new EmptyRouteGenerator())
            ->createBreadcrumbs();

        $breadcrumbs = array_slice($breadcrumbs, -2, 2);

        $paths = array_map(fn (Breadcrumb $breadcrumb) => $breadcrumb->pathname, $breadcrumbs);
        $paths = array_filter($paths, fn (string $pathname) => $isPathEmpty($pathname));

        return [ ...$paths ];
    }
}
