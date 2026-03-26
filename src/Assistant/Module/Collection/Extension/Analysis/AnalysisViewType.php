<?php

namespace Assistant\Module\Collection\Extension\Analysis;

enum AnalysisViewType: string
{
    case CROSS_REFERENCE = 'cross-reference';
    case FILENAME_MISMATCH = 'filename-mismatch';
    case EMPTY_METADATA = 'empty-metadata';
    case LOW_BITRATE = 'low-bitrate';
    case SIMILAR_ARTIST = 'similar-artist';
    case SIMILAR_PUBLISHER = 'similar-publisher';
    case SIMILAR_GENRE = 'similar-genre';
    case SUSPICIOUS_YEAR = 'suspicious-year';
    case RARE_GENRE = 'rare-genre';
    case RARE_KEY = 'rare-key';
    case POTENTIAL_DUPLICATE = 'potential-duplicate';

    public function label(): string
    {
        return match ($this) {
            self::CROSS_REFERENCE => 'Spójność systemów',
            self::FILENAME_MISMATCH => 'Nazwa pliku niezgodna z metadanymi',
            self::EMPTY_METADATA => 'Niekompletne dane',
            self::LOW_BITRATE => 'Niski bitrate',
            self::SIMILAR_ARTIST => 'Podobne nazwy wykonawców',
            self::SIMILAR_PUBLISHER => 'Podobne nazwy wydawców',
            self::SIMILAR_GENRE => 'Podobne nazwy gatunków',
            self::SUSPICIOUS_YEAR => 'Podejrzany rok',
            self::RARE_GENRE => 'Rzadki gatunek',
            self::RARE_KEY => 'Rzadka tonacja',
            self::POTENTIAL_DUPLICATE => 'Potencjalne duplikaty utworów',
        };
    }

    public function category(): AnalysisCategory
    {
        return match ($this) {
            self::CROSS_REFERENCE,
            self::FILENAME_MISMATCH => AnalysisCategory::COLLECTION,
            self::EMPTY_METADATA,
            self::LOW_BITRATE,
            self::SIMILAR_ARTIST,
            self::SIMILAR_PUBLISHER,
            self::SIMILAR_GENRE,
            self::SUSPICIOUS_YEAR,
            self::RARE_GENRE,
            self::RARE_KEY => AnalysisCategory::METADATA,
            self::POTENTIAL_DUPLICATE => AnalysisCategory::POTENTIAL_DUPLICATE,
        };
    }

    /** @return string[] */
    public function issueTypes(): array
    {
        return match ($this) {
            self::CROSS_REFERENCE => ['missing_on_disk', 'not_in_db', 'not_in_musly', 'not_in_db_but_in_musly'],
            self::FILENAME_MISMATCH => ['filename_metadata_mismatch'],
            self::EMPTY_METADATA => ['empty_metadata'],
            self::LOW_BITRATE => ['low_bitrate'],
            self::SIMILAR_ARTIST => ['similar_artist'],
            self::SIMILAR_PUBLISHER => ['similar_publisher'],
            self::SIMILAR_GENRE => ['similar_genre'],
            self::SUSPICIOUS_YEAR => ['suspicious_year'],
            self::RARE_GENRE => ['rare_genre'],
            self::RARE_KEY => ['rare_key'],
            self::POTENTIAL_DUPLICATE => ['potential_duplicate'],
        };
    }
}
