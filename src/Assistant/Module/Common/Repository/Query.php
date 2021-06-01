<?php

// @todo Repository -> Storage, (Persistence?)
namespace Assistant\Module\Common\Repository;

use Assistant\Module\Search\Extension\MinMaxInfo;
use Assistant\Module\Search\Extension\SearchCriteria;
use MongoDB\BSON\Regex;

final class Query
{
    public function __construct(
        private ?string $name,
        private ?string $artist,
        private ?string $title,
        private ?array $genres,
        private ?array $publishers,
        private MinMaxInfo|array|null $years,
        private ?array $initialKeys,
        private MinMaxInfo|array|null $bpm,
        private MinMaxInfo|array|null $indexedDates,
    ) {
    }

    public static function fromSearchCriteria(SearchCriteria $criteria): self
    {
        $query = new self(
            $criteria->getName(),
            $criteria->getArtist(),
            $criteria->getTitle(),
            $criteria->getGenres(),
            $criteria->getPublishers(),
            $criteria->getYears(),
            $criteria->getInitialKeys(),
            $criteria->getBpm(),
            $criteria->getIndexedDates(),
        );

        return $query;
    }

    public function toStorage(): array
    {
        $criteria = [];

        if ($this->name) {
            $criteria['$or'] = [
                [ 'artist' => new Regex($this->name, 'i') ],
                [ 'title' => new Regex($this->name, 'i') ],
                [ 'guid' => new Regex($this->name, 'i') ],
            ];
        }

        if ($this->artist) {
            $criteria['artist'] = new Regex($this->artist, 'i');
        }

        if ($this->title) {
            $criteria['title'] = new Regex($this->title, 'i');
        }

        if ($this->genres) {
            $genres = array_map(fn($genre) => new Regex('^' . $genre . '$', 'i'), $this->genres);

            if (count($genres) === 1) {
                $criteria['genre'] = $genres[0];
            } else {
                $criteria['genre'] = [ '$in' => $genres ];
            }
        }

        if ($this->publishers) {
            $publishers = array_map(fn($publisher) => new Regex('^' . $publisher . '$', 'i'), $this->publishers);

            if (count($publishers) === 1) {
                $criteria['publisher'] = $publishers[0];
            } else {
                $criteria['publisher'] = [ '$in' => $publishers ];
            }
        }

        if ($this->years) {
            if ($this->years instanceof MinMaxInfo) {
                $criteria['year'] = MinMaxInfoToStorageQuery::toStorage($this->years);
            } elseif (count($this->years) === 1) {
                $criteria['year'] = $this->years[0];
            } else {
                $criteria['year'] = [ '$in' => $this->years ];
            }
        }

        if ($this->initialKeys) {
            if (count($this->initialKeys) === 1) {
                $criteria['initial_key'] = $this->initialKeys[0];
            } else {
                $criteria['initial_key'] = [ '$in' => $this->initialKeys ];
            }
        }

        if ($this->bpm) {
            if ($this->bpm instanceof MinMaxInfo) {
                $criteria['bpm'] = MinMaxInfoToStorageQuery::toStorage($this->bpm);
            } elseif (count($this->bpm) === 1) {
                $criteria['bpm'] = $this->bpm[0];
            } else {
                $criteria['bpm'] = [ '$in' => $this->bpm ];
            }
        }

        if ($this->indexedDates) {
            if ($this->indexedDates instanceof MinMaxInfo) {
                $criteria['indexed_date'] = MinMaxInfoToStorageQuery::toStorage($this->indexedDates);
            } elseif (count($this->indexedDates) === 1) {
                $criteria['indexed_date'] = $this->indexedDates[0];
            } else {
                $criteria['indexed_date'] = [ '$in' => $this->indexedDates ];
            }
        }

        return $criteria;
    }
}
