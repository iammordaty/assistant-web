<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\ConsoleCommandRunner;
use Assistant\Module\Common\Extension\Messages;
use Assistant\Module\Common\Extension\Route;
use Assistant\Module\Common\Extension\RouteResolver;
use Assistant\Module\Track\Extension\DateDirectory;
use Assistant\Module\Track\Extension\FilenameFormat;
use Assistant\Module\Track\Task\RenameTrackTask;
use Cocur\BackgroundProcess\BackgroundProcess;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final readonly class TaskController
{
    private string $baseDir;

    public function __construct(
        private RouteResolver $routeResolver,
        private ConsoleCommandRunner $consoleCommandRunner,
        private RenameTrackTask $renameTrackTask,
        private Messages $messages,
        Config $config,
    ) {
        $this->baseDir = $config->get('base_dir');
    }

    public function calculateAudioData(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf('php %s/bin/console.php track:calculate-audio-data -w "%s"', $this->baseDir, $pathname);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function removeMetadata(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf(
            'php %s/bin/console.php track:remove-metadata --keep-supported-fields "%s"',
            $this->baseDir,
            $pathname
        );

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function cleanPathname(ServerRequest $request, Response $response): ResponseInterface
    {
        $pathname = $request->getParsedBodyParam('pathname');
        $command = sprintf('php %s/bin/console.php track:rename --clean "%s"', $this->baseDir, $pathname);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function rename(ServerRequest $request, Response $response): ResponseInterface
    {
        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        $format = FilenameFormat::tryFrom((string) $request->getParsedBodyParam('format'));

        if ($format === null) {
            $this->messages->addError('Nieznany format nazwy pliku - nie zmieniono nic.');

            return $response->withRedirect($redirectUrl);
        }

        $collectionItems = json_decode($request->getParsedBodyParam('elements'), true) ?: [];
        $options = $this->getRenameOptions($request);
        $failed = [];

        foreach ($collectionItems as $pathname) {
            $exitCode = $this->consoleCommandRunner->runSync($this->renameTrackTask, [
                'pathname' => $pathname,
                '--format' => $format->value,
                ...$options,
            ]);

            if ($exitCode !== 0) {
                $failed[] = basename($pathname);
            }
        }

        $this->reportRenameResult(count($collectionItems), $failed);

        return $response->withRedirect($redirectUrl);
    }

    /** Opcje zmiany nazwy wspólne dla całego zaznaczenia; rok i miesiąc są niezależnymi nadpisaniami */
    private function getRenameOptions(ServerRequest $request): array
    {
        $options = [];

        if ($request->getParsedBodyParam('mark_as_ready')) {
            $options['--mark-as-ready'] = true;
        }

        if (!$request->getParsedBodyParam('move_to_date_dir')) {
            return $options;
        }

        $options['--date-dir'] = true;

        $year = DateDirectory::tryParseYear($request->getParsedBodyParam('date_dir_year'));
        $month = DateDirectory::tryParseMonth($request->getParsedBodyParam('date_dir_month'));

        if ($year !== null) {
            $options['--date-dir-year'] = $year;
        }

        if ($month !== null) {
            $options['--date-dir-month'] = $month;
        }

        return $options;
    }

    /** @param string[] $failed */
    private function reportRenameResult(int $total, array $failed): void
    {
        if (!$failed) {
            $this->messages->addSuccess(sprintf('Zmieniono nazwę: %d.', $total));

            return;
        }

        $this->messages->addError(sprintf(
            'Nie udało się zmienić nazwy %d z %d plików: %s',
            count($failed),
            $total,
            implode(', ', $failed),
        ));
    }

    public function reindexSimilarTracksCollection(ServerRequest $request, Response $response): ResponseInterface
    {
        $command = sprintf('php %s/bin/console.php collection:reindex-similar-tracks', $this->baseDir);

        $backgroundProcess = new BackgroundProcess($command);
        $backgroundProcess->run();

        return $response->withJson([
            'command' => $command,
            'pid' => $backgroundProcess->getPid(),
        ]);
    }

    public function remove(ServerRequest $request, Response $response): ResponseInterface
    {
        $collectionItems = json_decode($request->getParsedBodyParam('elements'), true);

        foreach ($collectionItems as $pathname) {
            unlink($pathname);
        }

        $route = Route::create('directory.browse.incoming');
        $redirectUrl = $this->routeResolver->resolve($route);

        return $response->withRedirect($redirectUrl);
    }
}
