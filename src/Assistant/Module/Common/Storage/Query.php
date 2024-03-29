<?php

namespace Assistant\Module\Common\Storage;

use Assistant\Module\Common\Repository\LogRepository;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\MinMaxInfo;
use Assistant\Module\Search\Extension\SearchCriteria;
use MongoDB\BSON\Regex as MongoDBRegex;

/**
 * Ta klasa jest wyspecjalizowana — jej możliwości wyszukiwania ograniczone są do utworów oraz katalogów,
 * bez możliwości tworzenia zapytań do innych kolekcji oraz innych pól.
 *
 * @idea Do rozważenia: przenieść niniejszą klasę do Collection, rozdzielić względem Track i Direcotry.
 *       Wydzielić części, które mogą być przydatne w innych kolekcjach
 * @see DirectoryRepository
 * @see TrackRepository
 * @see LogRepository
 */
final class Query
{
    /**
     * @param string|null $name
     * @param Regex|string|null $guid
     * @param Regex|string|null $artist
     * @param Regex|string|null $title
     * @param Regex[]|string[]|null $genres
     * @param Regex[]|string[]|null $publishers
     * @param MinMaxInfo|int[]|null $years
     * @param string[]|null $initialKeys
     * @param MinMaxInfo|float[]|null $bpm
     * @param true|null $isFavorite
     * @param MinMaxInfo|\DateTimeInterface[]|null $indexedDates
     * @param string|null $parent
     * @param Regex[]|string[]|null $pathname
     */
    public function __construct(
        private ?string $name,
        private Regex|string|null $guid,
        private Regex|string|null $artist,
        private Regex|string|null $title,
        private ?array $genres,
        private ?array $publishers,
        private MinMaxInfo|array|null $years,
        private ?array $initialKeys,
        private MinMaxInfo|array|null $bpm,
        private true|null $isFavorite,
        private MinMaxInfo|array|null $indexedDates,
        private ?string $parent,
        private ?array $pathname,
    ) {
    }

    public static function fromSearchCriteria(SearchCriteria $criteria): self
    {
        $query = new self(
            $criteria->getName(),
            $criteria->getGuid(),
            $criteria->getArtist(),
            $criteria->getTitle(),
            $criteria->getGenres(),
            $criteria->getPublishers(),
            $criteria->getYears(),
            $criteria->getInitialKeys(),
            $criteria->getBpm(),
            $criteria->getIsFavorite(),
            $criteria->getIndexedDates(),
            $criteria->getParent(),
            $criteria->getPathname(),
        );

        return $query;
    }

    public function toStorage(): array
    {
        // Części wspólne wyciągnąć do prywatnych metod.
        // Zastanowić się jak ugryźć to, że konwersja MinMaxInfo jest w zewn. klasie, a Regex nie (widać to w use)

        $criteria = [];

        if ($this->name) {
            $criteria['$text'] = [ '$search' => $this->name ];
        }

        if ($this->guid) {
            $guid = $this->guid instanceof Regex
                ? new MongoDBRegex($this->guid->getPattern(), $this->guid->getFlags())
                : $this->guid;

            $criteria['guid'] = $guid;
        }

        if ($this->artist) {
            $artist = $this->artist instanceof Regex
                ? new MongoDBRegex($this->artist->getPattern(), $this->artist->getFlags())
                : $this->artist;

            $criteria['artist'] = $artist;
        }

        if ($this->title) {
            $title = $this->title instanceof Regex
                ? new MongoDBRegex($this->title->getPattern(), $this->title->getFlags())
                : $this->title;

            $criteria['title'] = $title;
        }

        if ($this->genres) {
            $genres = array_map(
                fn ($genre) => $genre instanceof Regex
                    ? new MongoDBRegex($genre->getPattern(), $genre->getFlags())
                    : $genre,
                $this->genres
            );

            $genre = count($genres) === 1
                ? $genres[0]
                : [ '$in' => $genres ];

            $criteria['genre'] = $genre;
        }

        if ($this->publishers) {
            $publishers = array_map(
                fn ($publisher) => $publisher instanceof Regex
                    ? new MongoDBRegex($publisher->getPattern(), $publisher->getFlags())
                    : $publisher,
                $this->publishers
            );

            $publisher = count($publishers) === 1
                ? $publishers[0]
                : [ '$in' => $publishers ];

            $criteria['publisher'] = $publisher;
        }

        if ($this->years) {
            if ($this->years instanceof MinMaxInfo) {
                $year = MinMaxInfoToStorageQuery::toStorage($this->years);
            } elseif (count($this->years) === 1) {
                $year = $this->years[0];
            } else {
                $year = [ '$in' => $this->years ];
            }

            $criteria['year'] = $year;
        }

        if ($this->initialKeys) {
            $initialKey = count($this->initialKeys) === 1
                ? $this->initialKeys[0]
                : [ '$in' => $this->initialKeys ];

            $criteria['initial_key'] = $initialKey;
        }

        if ($this->bpm) {
            if ($this->bpm instanceof MinMaxInfo) {
                $bpm = MinMaxInfoToStorageQuery::toStorage($this->bpm);
            } elseif (count($this->bpm) === 1) {
                $bpm = $this->bpm[0];
            } else {
                $bpm = [ '$in' => $this->bpm ];
            }

            $criteria['bpm'] = $bpm;
        }

        if ($this->isFavorite) {
            $criteria['is_favorite'] = $this->isFavorite;
        }

        if ($this->indexedDates) {
            if ($this->indexedDates instanceof MinMaxInfo) {
                $indexedDate = MinMaxInfoToStorageQuery::toStorage($this->indexedDates);
            } elseif (count($this->indexedDates) === 1) {
                $indexedDate = $this->indexedDates[0];
            } else {
                $indexedDate = [ '$in' => $this->indexedDates ];
            }

            $criteria['indexed_date'] = $indexedDate;
        }

        if ($this->parent) {
            $criteria['parent'] = $this->parent;
        }

        if ($this->pathname) {
            $pathname = array_map(
                fn ($pathname) => $pathname instanceof Regex
                    ? new MongoDBRegex($pathname->getPattern(), $pathname->getFlags())
                    : $pathname,
                $this->pathname
            );

            $pathname = count($pathname) === 1
                ? $pathname[0]
                : [ '$in' => $pathname ];

            $criteria['pathname'] = $pathname;
        }

        return $criteria;
    }
}
