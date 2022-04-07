<?php

namespace Assistant\Module\Common\Extension;

use Assistant\Module\Common\Model\LogEntry;
use Assistant\Module\Common\Repository\LogRepository;
use Assistant\Module\Common\Storage\Storage;
use DateTime;
use MongoDB\BSON\UTCDateTime;

final class LogView
{
    public function __construct(
        private readonly LogRepository $repository
    ) {
    }

    public function getLog(?DateTime $fromDate = null, ?int $page = null, ?int $limit = null): array
    {
        $query = $fromDate ? [ 'datetime' => [ '$gt' => new UTCDateTime($fromDatxe) ] ] : [];
        $sort = [ 'datetime' => Storage::SORT_DESC ];
        $skip = $page ? $limit * ($page - 1) : null;

        $cursor = $this->repository->findBy(
            $query,
            $sort,
            $limit,
            $skip
        );

        $log = array_map(static fn (LogEntry $logEntry) => $logEntry->toDto(), iterator_to_array($cursor));
        $count = $this->repository->count();

        return [
            'log' => $log,
            'count' => $count,
        ];
    }
}
