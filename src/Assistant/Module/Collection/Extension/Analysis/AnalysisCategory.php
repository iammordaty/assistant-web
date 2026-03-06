<?php

namespace Assistant\Module\Collection\Extension\Analysis;

enum AnalysisCategory: string
{
    case COLLECTION = 'collection';
    case METADATA = 'metadata';
    case POTENTIAL_DUPLICATE = 'potential_duplicate';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTION => 'Kolekcja',
            self::METADATA => 'Metadane',
            self::POTENTIAL_DUPLICATE => 'Potencjalne duplikaty',
        };
    }
}
