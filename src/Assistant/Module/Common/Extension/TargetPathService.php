<?php

namespace Assistant\Module\Common\Extension;

use Assistant\Module\File\Extension\PathFilterIterator;
use Assistant\Module\File\Extension\RecursiveDirectoryIterator;
use Twig\Node\Node;

/**
 * @todo Dokończyć; niech "Singles" i "Other" przekazywane będą z widoku, bez zgadywania
 * @todo Zmienić na klasę z metodami statycznymi
 */
class TargetPathService
{
    public static function factory(): TargetPathService
    {
        return new self();
    }

    /**
     * Zwraca ścieżkę katalogu, w którym powinien znaleźć się podany utwór
     *
     * @param Node $node
     * @return string
     */
    public function getTargetPath($node)
    {
        // TODO: Do przemyślenia: getTargetPath musi przyjmować SplFileInfo, ponieważ funkcja move wymaga docelowej nazwy (włącznie z katalogiem / plikiem przenoszonym)
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

        usort($tracks, function ($track1, $track2) {
            return $track1 === $track2 ? 0 : ($track1 > $track2 ? -1 : 1);
        });

        $subdir = strftime('%Y/%m. %B', $node->getMTime());

        // TODO: "Singles" powinno być w konfigu
        return sprintf('/%s/%s/%s', 'Singles', $subdir, $node->getBasename());
    }
}
