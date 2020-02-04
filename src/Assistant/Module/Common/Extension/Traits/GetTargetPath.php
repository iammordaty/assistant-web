<?php

namespace Assistant\Module\Common\Extension\Traits;

use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\IgnoredPathIterator;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Directory\Model\Directory;

/**
 * @todo Dokończyć
 * @todo Zmienić na klasę z metodami statycznymi
 */
trait GetTargetPath
{
    /**
     * Zwraca ścieżkę katalogu, w którym powinien znaleźć się podany utwór
     *
     * @param Model $node
     * @return string
     */
    private function getTargetPath($node)
    {
        // TODO: Do przemyślenia: getTargetPath musi przyjmować SplFileInfo, ponieważ funkcja move wymaga doceloweej nazwy (włącznie z katalogiem / plikiem przenoszonym)
        // TODO: Uspójnić; możliwe, że trzeba będzie przenieść do odrębnej klasy

        if ($node->isFile()) {
            $subdir = strftime('%Y/%m. %B', $node->getMTime());

            // TODO: "Other" powinno być w konfigu
            return sprintf('/%s/%s/%s', 'Other', $subdir, $node->getBasename());
        }

        $iterator = new PathFilterIterator(
            new RecursiveDirectoryIterator($node->getPathname(), RecursiveDirectoryIterator::SKIP_DOTS),
            $node->getPathname(),
            [ '@eaDir' ]
        );

        $iterator = new RecursiveDirectoryIterator($node->getPathname(), RecursiveDirectoryIterator::SKIP_DOTS);

        $tracks = [];

        // TODO: który iterator?
        foreach (new \RecursiveIteratorIterator($iterator) as $track) {
            $tracks[] = $track;
        }

    	if (empty($tracks)) {
            return null;
        }

        usort($tracks, function($track1, $track2) {
        	return $track1 === $track2 ? 0 : ($track1 > $track2 ? -1 : 1);
        });

        $subdir = strftime('%Y/%m. %B', $node->getMTime());

        // TODO: "Singles" powinno być w konfigu
        return sprintf('/%s/%s/%s', 'Singles', $subdir, $node->getBasename());
    }
}
