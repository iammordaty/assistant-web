<?php

namespace Assistant\Module\Search\Extension;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Storage\Storage;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use DateTime;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use MongoDB\BSON\UTCDateTime;
use Random\Randomizer;

final class RandomTrackListGenerator
{
    private const CACHE_DIR = '/var';
    private const CACHE_KEY = 'random_tracks';

    private array $debugInfo = [
        'from' => null,
        'limit' => null,
        'parts' => null,
        'limits' => null,
        'ranges' => null,
    ];

    public function __construct(
        private readonly Config $config,
        private readonly TrackRepository $trackRepository,
    ) {
    }

    /** @return Track[] */
    public function __invoke(int $limit): array
    {
        $cacheDir = $this->config->get('base_dir') . self::CACHE_DIR;
        $cache = new FilesystemCachePool(new Filesystem(new Local($cacheDir)));

        if ($cache->has(self::CACHE_KEY)) {
            return $cache->get(self::CACHE_KEY);
        }

        $tracks = iterator_to_array($this->getRandomTracks($limit));

        $tomorrow = (new DateTime())->modify('tomorrow midnight');

        $cache->set(
            key: self::CACHE_KEY,
            value: $tracks,
            ttl: (new DateTime())->diff($tomorrow)
        );

        return $tracks;
    }

    /** @return Track[] */
    public function getRandomTracks(int $limit): array
    {
        $oldestDate = $this->getOldestIndexedDate();

        $this->debugInfo['from'] = $oldestDate->format('d.m.Y');

        $parts = random_int(4, 6);

        $limits = $this->getLimitsPerRange($limit, $parts);
        $queries = $this->getRangeQueries($oldestDate, $limits);

        $this->debugInfo['limit'] = $limit;
        $this->debugInfo['parts'] = $parts;
        $this->debugInfo['limits'] = implode(', ', $limits);

        $result = [];

        foreach ($queries as $query) {
            $tracks = $this->trackRepository->aggregate($query);

            $result = array_merge($result, iterator_to_array($tracks));
        }

        $result = (new Randomizer())->shuffleArray($result);

        return $result;
    }

    public function __debugInfo(): ?array
    {
        return $this->debugInfo;
    }

    private function getRangeQueries(DateTime $startDate, array $ranges): array
    {
        $yearsDiff = $startDate->diff(new DateTime())->y;
        $yearsPerRange = ceil($yearsDiff / count($ranges));

        $queries = array_map(function ($limit) use ($startDate, $yearsPerRange): array {
            $rangeStartDate = clone $startDate;
            $rangeStartDate
                ->setDate($rangeStartDate->format('Y'), 1, 1)
                ->setTime(0, 0);

            $yearModifier = sprintf('+%d years', $yearsPerRange);

            $rangeEndDate = clone $startDate;
            $rangeEndDate
                ->modify($yearModifier)
                ->modify('-1 day')
                ->setDate($rangeEndDate->format('Y'), 12, 31)
                ->setTime(23, 59, 59);

            $this->debugInfo['ranges'][] = sprintf(
                '%s - %s (%s)',
                $rangeStartDate->format('d.m.Y'),
                $rangeEndDate->format('d.m.Y'),
                $limit
            );

            $startDate->modify($yearModifier);

            return [
                [
                    '$match' => [
                        'indexed_date' => [
                            '$gte' => new UTCDateTime($rangeStartDate->getTimestamp() * 1000),
                            '$lte' => new UTCDateTime($rangeEndDate->getTimestamp() * 1000),
                        ]
                    ]
                ],
                [
                    '$sample' => [ 'size' => $limit ],
                ],
            ];
        }, $ranges);

        return $queries;
    }

    private function getLimitsPerRange(int $target, int $parts)
    {
        $min = ceil($target * 0.1);
        $max = ceil($target * 0.15);

        $limits = array_map(
            fn (): int => random_int($min, $max),
            array_fill(0, $parts, null)
        );

        $maxIndex = $parts - 1;
        $getRandomIndex = fn (): int => random_int(0, $maxIndex);

        do {
            $randomIndex = $getRandomIndex();
            $limits[$randomIndex]++;

            if (random_int(0, 3) !== 0) {
                $randomIndex = $getRandomIndex();

                if ($limits[$randomIndex] - 1 > $min) {
                    $limits[$randomIndex]--;
                };
            }
        } while (array_sum($limits) < $target);

        return $limits;
    }

    private function getOldestIndexedDate(): ?DateTime
    {
        $track = $this->trackRepository->getOneBy(
            new SearchCriteria(),
            [ 'indexed_date' => Storage::SORT_ASC ],
        );

        $oldestDate = $track->getIndexedDate();

        return $oldestDate;
    }
}
