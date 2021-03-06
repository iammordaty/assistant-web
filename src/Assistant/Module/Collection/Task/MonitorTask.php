<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Collection\Extension\Finder;
use Assistant\Module\Common\Task\AbstractTask;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task monitorujący zmiany w strukturze kolekcji
 */
final class MonitorTask extends AbstractTask
{
    private array $parameters;

    public function isEnabled(): bool
    {
        // FIXME: Task wymaga dokończenia i przetestowania

        return false;
    }

    protected function configure()
    {
        $this->parameters = $this->app->container['parameters']['collection'];

        $this
            ->setName('collection:monitor')
            ->setDescription('Monitors changes in collection filesystem');
    }

    /**
     * Rozpoczyna proces monitorowania zmian w kolekcji
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fd = inotify_init();

        stream_set_blocking($fd, 0);

        $descriptors = [];

        foreach ($this->getNodesToMonitor() as $node) {
            if ($node->isFile()) {
                continue;
            }

            $wd = inotify_add_watch(
                $fd,
                $node->getPathname(),
                IN_CREATE | IN_MODIFY | IN_MOVE | IN_DELETE
            );

            $descriptors[$wd] = $node->getPathname();

            unset($wd);
        }

        while (true) {
            $events = inotify_read($fd);

            if ($events === false) {
                continue;
            }

            foreach ($events as $event) {
                $this->comment(sprintf('[debug] %d/%d: ', $event, $events), false);

                $pathname = sprintf('%s/%s', $descriptors[$event['wd']], $event['name']);

                switch ($event['mask']) {
                    case IN_ALL_EVENTS:
                        $this->info(sprintf('[debug] IN_ALL_EVENTS: %s', $pathname));
                        break;

                    case IN_CREATE | IN_ISDIR:
                        $this->info(sprintf('IN_CREATE | IN_ISDIR: %s', $pathname));
                        $this->index($pathname);

                        $wd = inotify_add_watch(
                            $fd,
                            $pathname,
                            IN_CREATE | IN_MODIFY | IN_MOVE | IN_DELETE
                        );

                        $descriptors[$wd] = $pathname;
                        break;

                    case IN_CREATE:
                        $this->info(sprintf('IN_CREATE: %s', $pathname));
                        $this->calculateAudioData($pathname);
                        break;

                    case IN_CLOSE_WRITE:
                        $this->info(sprintf('IN_CLOSE_WRITE: %s', $pathname));
                        $this->index($pathname);
                        break;

                    case IN_MOVED_TO | IN_ISDIR:
                        $wd = inotify_add_watch(
                            $fd,
                            $pathname,
                            IN_CREATE | IN_MODIFY | IN_MOVE | IN_DELETE
                        );

                        $descriptors[$wd] = $pathname;
                        // no break

                    case IN_MOVED_TO:
                        $this->info(sprintf('IN_MOVED_TO: %s', $pathname));
                        $this->index($pathname);
                        break;

                    case IN_MOVED_FROM:
                    case IN_MOVED_FROM | IN_ISDIR:
                        $this->info(sprintf('IN_MOVED_FROM: %s', $pathname));
                        $this->delete($pathname);

                        break;

                    case IN_DELETE:
                    case IN_DELETE | IN_ISDIR:
                        $this->info(sprintf('IN_DELETE: %s', $pathname));
                        $this->delete($pathname);
                        break;
                }
            }
        }

        fclose($fd);

        unset($input, $output);

        return self::SUCCESS;
    }

    /**
     * @return Finder|SplFileInfo[]
     */
    private function getNodesToMonitor(): Finder
    {
        return Finder::create([
            'pathname' => $this->parameters['root_dir'],
            'recursive' => true,
            'restrict' => $this->parameters['indexed_dirs'],
        ]);
    }

    /**
     * Uruchamia task zapisujący tonację oraz liczbę uderzeń na minutę do metadanych utworu muzycznego
     *
     * @param string $pathname
     */
    private function calculateAudioData(string $pathname)
    {
        /*
        (new \Cocur\BackgroundProcess\BackgroundProcess(
            sprintf('php bin/console.php track:calculate-audio-data -r -w "%s"', $pathname)
        ))->run();
        */
    }

    /**
     * Uruchamia task indeksujący podaną ścieżki
     *
     * @param string $pathname
     */
    private function index(string $pathname)
    {
        /*
        (new \Cocur\BackgroundProcess\BackgroundProcess(
            sprintf('php bin/console.php collection:index "%s"', $pathname)
        ))->run();
        */
    }

    /**
     * Uruchamia task usuwający podaną ścieżkę
     *
     * @param string $pathname
     */
    private function delete(string $pathname)
    {
        /*
        (new \Cocur\BackgroundProcess\BackgroundProcess(
            sprintf('php bin/console.php collection:clean "%s"', $pathname)
        ))->run();
        */
    }
}
