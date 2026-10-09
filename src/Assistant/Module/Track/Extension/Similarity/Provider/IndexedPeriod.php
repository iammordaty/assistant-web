<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Track\Model\Track;
use DateTime;
use DateTimeInterface;

/**
 * AI: dostawca podobieństwa oparty na okresie dodania utworu do kolekcji. Utwory dodane w zbliżonym
 * czasie, niezależnie od tego, czy leżą w Singles, czy w Other, zwykle łączy wspólny charakter
 * z danego okresu. Data dodania pochodzi z katalogu rok/miesiąc, więc liczą się wyłącznie rok i miesiąc.
 */
final class IndexedPeriod extends AbstractProvider
{
    /** {@inheritDoc} */
    public const string NAME = 'IndexedPeriod';

    /** {@inheritDoc} */
    protected array $similarityMap = [
        0 => self::MAX_SIMILARITY_VALUE,
        1 => 95,
        2 => 85,
        3 => 75,
        4 => 65,
        5 => 55,
        6 => 50,
    ];

    /** Wartość podobieństwa dla utworów spoza mapy, dostępnych po poszerzeniu okna */
    private const int DISTANT_SIMILARITY_VALUE = 40;

    public function __construct(private array $parameters)
    {
    }

    /** {@inheritDoc} */
    public function getSimilarityValue(Track $baseTrack, Track $comparedTrack): ?int
    {
        $baseDate = $baseTrack->getIndexedDate();
        $comparedDate = $comparedTrack->getIndexedDate();

        if (!$baseDate || !$comparedDate) {
            return null;
        }

        $distance = abs(self::toMonths($baseDate) - self::toMonths($comparedDate));

        return $this->similarityMap[$distance] ?? self::DISTANT_SIMILARITY_VALUE;
    }

    /** {@inheritDoc} */
    public function getCriteria(Track $baseTrack): ?MinMaxInfo
    {
        return $this->getCriteriaWithin($baseTrack, $this->parameters['tolerance']);
    }

    /** Zwraca kryteria dla okna o podanej szerokości (w miesiącach, w każdą stronę) */
    public function getCriteriaWithin(Track $baseTrack, int $tolerance): ?MinMaxInfo
    {
        $indexedDate = $baseTrack->getIndexedDate();

        if (!$indexedDate) {
            return null;
        }

        $firstDayOfMonth = DateTime::createFromInterface($indexedDate)
            ->modify('first day of this month')
            ->setTime(0, 0);

        $minMaxInfo = MinMaxInfo::create([
            MinMaxInfo::GREATER_THAN_OR_EQUAL => (clone $firstDayOfMonth)->modify(sprintf('-%d months', $tolerance)),
            MinMaxInfo::LESS_THAN => (clone $firstDayOfMonth)->modify(sprintf('+%d months', $tolerance + 1)),
        ]);

        return $minMaxInfo;
    }

    /**
     * Kolejne szerokości okna, sprawdzane do skutku przy zbyt małej liczbie kandydatów
     *
     * @return int[]
     */
    public function getTolerances(): array
    {
        return range($this->parameters['tolerance'], $this->parameters['max_tolerance']);
    }

    /** Minimalna liczba kandydatów, przy której okno nie jest już poszerzane */
    public function getMinTracks(): int
    {
        return $this->parameters['min_tracks'];
    }

    private static function toMonths(DateTimeInterface $date): int
    {
        return (int) $date->format('Y') * 12 + (int) $date->format('n');
    }
}
