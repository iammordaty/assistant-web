<?php

namespace Assistant\Module\Common\Controller;

use Assistant\Module\Common\Extension\Config;
use PhpExtended\Tail\Tail;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class LogController
{
    private const AVAILABLE_LOGS = [ 'debug', 'error' ];

    private string $baseDir;

    public function __construct(Config $config, private Twig $view)
    {
        $this->baseDir = $config->get('base_dir');
    }

    public function index(Request $request, Response $response): Response
    {
        $log = $request->getQueryParams()['log'] ?? null;

        if ($log && !in_array($log, self::AVAILABLE_LOGS)) {
            throw new HttpNotFoundException($request);
        }

        $logs = $log ? [ $log ] : self::AVAILABLE_LOGS;

        $names = array_map(fn($log): string => sprintf('%s/app/logs/app.%s.log', $this->baseDir, $log), $logs);
        $maxLines = $log ? 100 : 5;

        $contents = array_map(fn($filename): array => $this->read($filename, $maxLines), $names);
        $mtimes = array_map(fn($filename): bool|int => filemtime($filename), $names);

        return $this->view->render($response, '@common/log/index.twig', [
            'menu' => 'log',
            'maxLines' => $maxLines,
            'availableLogs' => $logs,
            'logContents' => array_combine($logs, $contents),
            'logMtimes' => array_combine($logs, $mtimes),
        ]);
    }

    public function ajax(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $log = $queryParams['log'] ?? null;

        if ($log && !in_array($log, self::AVAILABLE_LOGS)) {
            throw new HttpNotFoundException($request);
        }

        $filename = sprintf('%s/app/logs/app.%s.log', $this->baseDir, $log);

        $maxLines = $queryParams['lines'] ?? null;
        $logContent = $this->read($filename, $maxLines);
        $logMtime = filemtime($filename);

        return $this->view->render($response, '@common/log/view.twig', [
            'logContent' => $logContent,
            'logMtime' => $logMtime,
        ]);
    }

    private function read($filename, $maxLines): array
    {
        $lines = (new Tail($filename))->smart($maxLines + 1);

        if (empty($lines)) {
            return [];
        }

        $segments = array_map(function ($line) {
            $matches = [];

            preg_match('/\[(.+)\]\s(\w+)\s(.+?)({.+?}})\s?(?:({.+}))?/', $line, $matches);

            if (empty($matches)) {
                preg_match('/\[(.+)\]\s(\w+)\s(.+?)({.+?}}?)\s?(?:({.+}))?/', $line, $matches);
            }

            $context = json_decode($matches[4], true);

            if (isset($matches[5])) {
                $context = array_merge($context ?? [], json_decode($matches[5], true) ?? []);
            }

            $pathname = [];

            if (isset($context['pathname'])) {
                $pathname = [
                    'short' => basename(dirname($context['pathname'])) . DIRECTORY_SEPARATOR . basename($context['pathname']),
                    'full' => $context['pathname'],
                ];
            }

            $task = $context['task'] ?? null;
            $command = $context['command'] ?? null;

            $ignoredKeys = [ 'pathname', 'task', 'command' ];
            $ctx = array_diff_key(json_decode($matches[4], true) ?: [], array_flip($ignoredKeys));

            return [
                'raw' => $line,
                'date' => $matches[1],
                'level' => $matches[2],
                'message' => trim($matches[3]),
                'pathname' => $pathname,
                'task' => $task ?: $command,
                'context' => !empty($ctx) ? json_encode($ctx, JSON_PRETTY_PRINT) : null,
            ];
        }, array_reverse($lines));

        return $segments;
    }
}
