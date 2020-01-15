<?php

namespace Assistant\Module\Common\Controller;

use PhpExtended\Tail\Tail;

class LogController extends AbstractController
{
    public function index()
    {
        $availableLogs = [ 'debug', 'error' ];

        $log = $this->app->request()->get('log');

        if ($log && !in_array($log, $availableLogs)) {
            $this->app->notFound();
        }

        $logs = $log ? [ $log ] : $availableLogs;

        $names = array_map(function ($log) {
            return BASE_DIR . "/app/logs/app.$log.log";
        }, $logs);

        $maxLines = $log ? 100 : 5;

        $contents = array_map(function ($filename) use ($maxLines) {
            return $this->read($filename, $maxLines);
        }, $names);

        $mtimes = array_map(function ($filename) {
            return filemtime($filename);
        }, $names);

        return $this->app->render('@common/log/index.twig', [
            'menu' => 'log',
            'maxLines' => $maxLines,
            'availableLogs' => $logs,
            'logContents' => array_combine($logs, $contents),
            'logMtimes' => array_combine($logs, $mtimes),
        ]);
    }

    public function ajax()
    {
        $availableLogs = [ 'debug', 'error' ];

        $log = $this->app->request()->get('log');

        if (!in_array($log, $availableLogs)) {
            $this->app->notFound();
        }

        $filename = BASE_DIR . "/app/logs/app.$log.log";
        
        $maxLines = $this->app->request()->get('lines');
        $logContent = $this->read($filename, $maxLines);
        $logMtime = filemtime($filename);

        return $this->app->render('@common/log/view.twig', [
            'logContent' => $logContent,
            'logMtime' => $logMtime,
        ]);
    }

    private function read($filename, $maxLines)
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
                $context = array_merge($context, json_decode($matches[5], true));
            }

            $pathname = [];

            if (isset($context['pathname'])) {
                $pathname = [
                    'short' => basename(dirname($context['pathname'])) . DIRECTORY_SEPARATOR . basename($context['pathname']),
                    'full' => $context['pathname'],
                ];
            }

            $task = isset($context['task']) ? $context['task'] : null;
            $command = isset($context['command']) ? $context['command'] : null;

            $blacklistedKeys = [ 'memory_usage', 'procId', 'pathname', 'task', 'command', 'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction' ];

            $ctx = array_diff_key(json_decode($matches[4], true) ?: [], array_flip($blacklistedKeys));

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
