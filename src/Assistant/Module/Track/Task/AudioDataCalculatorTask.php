<?php

namespace Assistant\Module\Track\Task;

use Assistant\Module\Common;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\SplFileInfo;

use Curl\Curl;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task mający za zadanie obliczenie tonacji oraz liczby uderzeń na mintutę
 * w zadanym pliku (utworze muzycznym)
 */
class AudioDataCalculatorTask extends AbstractTask
{
    /**
     * @var array
     */
    private $parameters;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->parameters = $this->app->container->parameters['collection'];

        $this
            ->setName('track:calculate-audio-data')
            ->setDescription('Calculates BPM and initial key for track(s)')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to search tracks to calculate BPM and initial key',
                $this->parameters['root_dir']
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->stats = [
            'file' => 0,
            'dir' => 0,
            'error' => 0,
        ];
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id3 = new Common\Extension\GetId3\Adapter();

        foreach ($this->getIterator($input->getArgument('pathname')) as $node) {
            if ($node->isDir() === true) {
                $this->stats['dir']++;

                continue;
            }

            $audioData = $this->getAudioData($node);

            try {
                $id3
                    ->setFile($node)
                    ->writeId3v2Metadata($audioData);
                
                $this->stats['file']++;

            } catch (Common\Extension\GetId3\Exception\WriterException $e) {
                $this->stats['error']++;

                $this->error($e->getMessage());
            } finally {
                unset($node, $audioData);
            }

            $this->info('.', false);
        }

        $this->showSummary();

        unset($input, $output);
    }

    /**
     * @param string $pathname
     * @return IgnoredPathIterator|\ArrayIterator
     */
    private function getIterator($pathname)
    {
        if (is_file($pathname)) {
            return new \ArrayIterator(
                [
                    new SplFileInfo(
                        $pathname,
                        str_replace(sprintf('%s/', $this->parameters['root_dir']), '', $pathname)
                    )
                ]
            );
        }

        return new PathFilterIterator(
            new RecursiveDirectoryIterator($pathname),
            $this->parameters['root_dir'],
            $this->parameters['excluded_dirs']
        );
    }

    /**
     * Wyświetla podsumowanie procesu indeksowania
     */
    private function showSummary()
    {
        $this->info('');
        $this->info('Zakończono.');
        $this->info('');

        $this->info(sprintf('Liczba przetworzonych utworów: %d', $this->stats['file']));
        $this->info(sprintf('Liczba elementów nie dodanych z powodu błędu: %d', $this->stats['error']));

        $this->info('');
        $this->info(sprintf('Maksymalne użycie pamięci: %.3f MB', (memory_get_peak_usage() / (1024 * 1024))));
    }

    /**
     *
     * @param SplFileInfo $node
     * @return array
     * @throws \RuntimeException
     */
    private function getAudioData(SplFileInfo $node)
    {
        $curl = new Curl();

        $response = $curl->get(
            sprintf('%s/track/%s', 'http://assistant-backend', urlencode($node->getRelativePathname()))
        );

        if ($curl->error === true) {
            throw new \RuntimeException($curl->errorMessage, $curl->errorCode ?: 500);
        }

        $curl->close();

        return (array) $response;
    }
}
