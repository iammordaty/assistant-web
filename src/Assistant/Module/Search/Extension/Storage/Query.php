<?php

namespace Assistant\Module\Search\Extension\Storage;

use Assistant\Module\Common\Repository\LogRepository;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Search\Extension\Criteria\Not;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Criteria\SearchCriteriaField;
use MongoDB\BSON\Regex as MongoDBRegex;

/**
 * Ta klasa jest wyspecjalizowana — jej możliwości wyszukiwania ograniczone są do utworów oraz katalogów,
 * bez możliwości tworzenia zapytań do innych kolekcji oraz innych pól.
 *
 * @idea Do rozważenia: przenieść niniejszą klasę do Collection, rozdzielić względem Track i Directory.
 *       Wydzielić części, które mogą być przydatne w innych kolekcjach
 * @see DirectoryRepository
 * @see TrackRepository
 * @see LogRepository
 */
final class Query
{
    /**
     * @param string|null $name
     * @param SearchCriteriaField|array|string|null $guid
     * @param SearchCriteriaField|string|null $artist
     * @param SearchCriteriaField|string|null $title
     * @param SearchCriteriaField[]|string[]|null $genres
     * @param SearchCriteriaField[]|string[]|null $publishers
     * @param MinMaxInfo|int[]|null $years
     * @param string[]|null $initialKeys
     * @param MinMaxInfo|float[]|null $bpm
     * @param true|null $isFavorite
     * @param MinMaxInfo|\DateTimeInterface[]|null $indexedDates
     * @param string|null $parent
     * @param SearchCriteriaField[]|string[]|null $pathname
     */
    public function __construct(
        private ?string $name,
        private SearchCriteriaField|array|string|null $guid,
        private SearchCriteriaField|string|null $artist,
        private SearchCriteriaField|string|null $title,
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
        $criteria = [];

        if ($this->name) {
            $criteria['$text'] = [ '$search' => $this->name ];
        }

        if ($this->guid) {
            $criteria['guid'] = self::toStorageValue($this->guid);
        }

        if ($this->artist) {
            $criteria['artists'] = self::fieldToStorage($this->artist);
        }

        if ($this->title) {
            $criteria['title'] = self::fieldToStorage($this->title);
        }

        if ($this->genres) {
            $criteria['genre'] = self::toStorageValue($this->genres, transform: true);
        }

        if ($this->publishers) {
            $criteria['publisher'] = self::toStorageValue($this->publishers, transform: true);
        }

        if ($this->years) {
            $criteria['year'] = self::toStorageValue($this->years);
        }

        if ($this->initialKeys) {
            $criteria['initial_key'] = self::toStorageValue($this->initialKeys);
        }

        if ($this->bpm) {
            $criteria['bpm'] = self::toStorageValue($this->bpm);
        }

        if ($this->isFavorite) {
            $criteria['is_favorite'] = $this->isFavorite;
        }

        if ($this->indexedDates) {
            $criteria['indexed_date'] = self::toStorageValue($this->indexedDates);
        }

        if ($this->parent) {
            $criteria['parent'] = self::fieldToStorage($this->parent);
        }

        if ($this->pathname) {
            $criteria['pathname'] = self::toStorageValue($this->pathname, transform: true);
        }

        return $criteria;
    }

    private static function toStorageValue(mixed $field, bool $transform = false): mixed
    {
        if (!is_array($field)) {
            return self::fieldToStorage($field);
        }

        $values = $transform
            ? array_map(self::fieldToStorage(...), $field)
            : $field;

        return count($values) === 1
            ? $values[0]
            : [ '$in' => $values ];
    }

    private static function fieldToStorage(mixed $field): mixed
    {
        return match (true) {
            $field instanceof MinMaxInfo => MinMaxInfoToStorageQuery::toStorage($field),
            $field instanceof Not => [ '$exists' => true, '$ne' => $field->getValue() ],
            $field instanceof Regex => new MongoDBRegex($field->getPattern(), $field->getFlags()),

            default => $field,
        };
    }
}
