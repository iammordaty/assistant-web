<?php

namespace Assistant\Module\Track\Extension\Similarity;

use Assistant\Module\Track\Extension\Similarity\Provider\AbstractProvider;
use Assistant\Module\Track\Extension\Similarity\Provider\Bpm;
use Assistant\Module\Track\Extension\Similarity\Provider\Genre;
use Assistant\Module\Track\Extension\Similarity\Provider\MusicalKey;
use Assistant\Module\Track\Extension\Similarity\Provider\Musly;
use Assistant\Module\Track\Extension\Similarity\Provider\Year;
use Assistant\Module\Track\Model\Track;

/**
 * Atrapa dostawcy zwracająca zadaną wartość.
 *
 * Similarity odczytuje wagę przez stałą $provider::NAME, więc każda nazwa wymaga osobnej klasy -
 * atrapa generowana przez PHPUnit odziedziczyłaby pustą nazwę z interfejsu i zostałaby odrzucona
 * jako duplikat.
 */
abstract class FixedValueProvider extends AbstractProvider
{
    public function __construct(private ?int $similarityValue)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        return $this->similarityValue;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): null
    {
        return null;
    }
}

final class FixedValueBpmProvider extends FixedValueProvider
{
    public const string NAME = Bpm::NAME;
}

final class FixedValueGenreProvider extends FixedValueProvider
{
    public const string NAME = Genre::NAME;
}

final class FixedValueMusicalKeyProvider extends FixedValueProvider
{
    public const string NAME = MusicalKey::NAME;
}

final class FixedValueMuslyProvider extends FixedValueProvider
{
    public const string NAME = Musly::NAME;
}

final class FixedValueYearProvider extends FixedValueProvider
{
    public const string NAME = Year::NAME;
}
