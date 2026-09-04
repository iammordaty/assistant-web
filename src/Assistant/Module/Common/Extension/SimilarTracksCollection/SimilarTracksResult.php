<?php

namespace Assistant\Module\Common\Extension\SimilarTracksCollection;

use SplFileInfo;

// Sprawdzić, czy SimilarTracks może używać tego jako klasy bazowej albo serwisu (jak Config, Finder itp.)
// zwracając oczywiście obiekty typu Track, a nie SplFileInfo. Jeśli tak, to należałoby uspójnić nazewnictwo klas.
final readonly class SimilarTracksResult
{
    private function __construct(
        private SplFileInfo $firstTrack,
        private SplFileInfo $secondTrack,
        private float $similarityValue,
    ) {
    }

    public static function factory(
        int $collectionSize,
        SplFileInfo|string $firstTrack,
        SplFileInfo|string $secondTrack,
        float $distance,
    ): self {
        if (is_string($firstTrack)) {
            $firstTrack = new SplFileInfo($firstTrack);
        }
        if (is_string($secondTrack)) {
            $secondTrack = new SplFileInfo($secondTrack);
        }

        $similarityValue = self::calculateSimilarityValue($distance, $collectionSize);

        return new self($firstTrack, $secondTrack, $similarityValue);
    }

    // do zastanowienia się, czy to utrzymywać
    public function getFirstTrack(): SplFileInfo
    {
        return $this->firstTrack;
    }

    public function getSecondTrack(): SplFileInfo
    {
        return $this->secondTrack;
    }

    public function getSimilarityValue(): float
    {
        return $this->similarityValue;
    }

    /**
     * Przelicza odległość zwróconą przez musly na wartość podobieństwa w skali 0-100.
     *
     * Po normalizacji Mutual Proximity odległość wyraża wzajemny udział kolekcji znajdujący się
     * bliżej (d ≈ (r + r') / N), więc najlepsza osiągalna wartość to 2/N, a mediana to 1. Stąd skala
     * logarytmiczna: rozciąga czołówkę listy, gdzie odległości różnią się o rzędy wielkości, i —
     * dzięki odniesieniu do rozmiaru kolekcji — pozostaje porównywalna między bibliotekami różnej
     * wielkości. Wartość 100 oznacza parę wzajemnie najbliższych sąsiadów, a 0 odległość mediany.
     */
    private static function calculateSimilarityValue(float $distance, int $collectionSize): float
    {
        if ($collectionSize < 4) {
            // w tak małej kolekcji percentyl nie ma rozdzielczości
            return 100.0;
        }

        // 0 jest zwracane dla utworu porównanego z samym sobą
        $bestPossible = 2.0 / $collectionSize;
        $distance = min(max($distance, $bestPossible), 1.0);

        $normalized = log10(1.0 / $distance) / log10($collectionSize / 2.0);

        return round(100 * max(0.0, min(1.0, $normalized)), 2);
    }
}
