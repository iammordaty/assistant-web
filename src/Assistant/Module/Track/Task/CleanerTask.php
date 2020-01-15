<?php

namespace Assistant\Module\Track\Task;

use ArrayIterator;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Extension\GetId3\Exception\WriterException;
use Assistant\Module\Common\Task\AbstractTask;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\SplFileInfo;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task indeksujący utwory znajdujące się w kolekcji
 */
class CleanerTask extends AbstractTask
{
    /**
     * Tablica asocjacyjna zawierająca statystyki zadania
     *
     * @var array
     */
    private $stats;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $id3WriterOptions = [
        'tag_encoding' => 'UTF-8',
        'tagformats' => [ 'id3v2.3' ],
        'remove_other_tags' => true,
    ];

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->parameters = $this->app->container->parameters['collection'];

        $this
            ->setName('track:clean')
            ->setDescription('Cleans tracks')
            ->addArgument(
                'pathname',
                InputArgument::OPTIONAL,
                'Pathname to clean'
            )->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Process directories recursively');
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->stats = [
            'processed' => 0,
            'cleaned' => 0,
            'error' => [ 'tags' => 0, 'other' => 0 ],
        ];
    }

    /**
     * Rozpoczyna proces indeksowania kolekcji
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->app->log->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $iterator = $this->getIterator($input->getArgument('pathname'), $input->getOption('recursive'));

        foreach ($iterator as $node) {
            if ($node->isDir()) {
                continue;
            }

            $this->app->log->debug('Processing node', [ 'pathname' => $node->getPathname() ]);

            $this->stats['processed']++;

            $id3Adapter = new Id3Adapter($node);
            $id3Adapter->setFile($node);
            $id3Adapter->setId3WriterOptions($this->id3WriterOptions);

            $metadata = $this->getMetadataFromFilename($node);

            $newFilename = sprintf(
                '%s/%s - %s.%s',
                $node->getPath(),
                $metadata['artist'],
                $metadata['title'],
                $node->getExtension()
            );

            $this->app->log->debug('Writing data to track', [
                'artist' => $metadata['artist'],
                'title' => $metadata['title'],
                'newFilename' => $newFilename,
            ]);

            try {
                $id3Adapter->writeId3v2Metadata($metadata, true);

                rename($node->getPathname(), $newFilename);
                chmod($node->getPathname(), 0777);

                $this->app->log->debug('Track processed successfully');

                $this->stats['cleaned']++;
            } catch (WriterException $e) {
                $this->stats['error']['tags']++;

                $this->app->log->error($e->getMessage(), [
                    'pathname' => $node->getPathname(),
                    'metadata' => $metadata,
                    'id3WriterErrors' => $id3Adapter->getWriterErrors(),
                    'id3WriterWarnings' => $id3Adapter->getWriterWarnings(),
                ]);
            } catch (\Exception $e) {
                $this->stats['error']['other']++;

                $this->app->log->critical('Cleaning track failed: ' . $e->getMessage(), [
                    'pathname' => $node->getPathname(),
                    'metadata' => $metadata,
                    'exception' => $e,
                ]);
            } finally {
                unset($node, $metadata, $id3Adapter, $newFilename);
            }
        }

        $this->app->log->info('Task finished', $this->stats);

        unset($input, $output);
    }

    private function getMetadataFromFilename(SplFileInfo $node)
    {
        $basename = $node->getBasename('.' . $node->getExtension());

        $metadata = array_map('trim', explode(' - ', $basename, 2));

        $artist = $metadata[0];
        $title = $metadata[1];

        return [
            'artist' => $this->getCleanedArtist($artist),
            'title' => $this->getCleanedTitle($title),
        ];
    }

    private function getCleanedArtist($artist)
    {
        $artist = strtolower($artist);

        $artist = str_replace('ft.', 'feat.', $artist);
        $artist = str_replace('ft', 'feat.', $artist);
        $artist = str_replace('vs', 'vs.', $artist);
        $artist = preg_replace('/\.+/', '.', $artist);
        $artist = ucwords($artist, " \t\r\n\f\v[]-1234567890");

        // TODO: Usuwać wielokrotne spacje

        return $artist;
    }

    private function getCleanedTitle($title)
    {
        $title = strtolower($title);

        $title = str_replace('(', '[', $title);
        $title = str_replace(')', ']', $title);
        $title = ucwords($title, " \t\r\n\f\v[]-1234567890");

        // TODO: Spróbować ogarnąć taki przypadek:
        // Space Cowboy - Crazy Talk [Pique & Nique's 'you Will Miss Me' Mix]
        // powinno powstać:
        // Space Cowboy - Crazy Talk [Pique & Nique's 'You Will Miss Me' Mix]

        // TODO: wycinać domeny i nazwy wytwórni
        // TODO: Usuwać wielokrotne spacje

        return $title;
    }

    /**
     * @param string $pathname
     * @param bool $recursive
     * @return PathFilterIterator|RecursiveIteratorIterator|ArrayIterator
     */
    private function getIterator($pathname, $recursive)
    {
        if (is_file($pathname)) {
            $relativePathname = str_replace(sprintf('%s/', $this->parameters['root_dir']), '', $pathname);

            return new ArrayIterator([ new SplFileInfo($pathname, $relativePathname) ]);
        }

        $iterator = new PathFilterIterator(
            new RecursiveDirectoryIterator($pathname),
            $this->parameters['root_dir'],
            [ ]
        );

        if ($recursive === true) {
            $iterator = new RecursiveIteratorIterator(
                $iterator,
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        }

        return $iterator;
    }
}
