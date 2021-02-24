<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Track\Model\Track;
use DateTime as DateTime;
use MongoDB\BSON\UTCDateTime;

/**
 * Repozytorium obiektów Track
 */
class TrackRepository extends AbstractObjectRepository
{
    /**
     * {@inheritDoc}
     */
    protected const COLLECTION = 'tracks';

    /**
     * {@inheritDoc}
     */
    protected const MODEL = Track::class;

    /**
     * {@inheritDoc}
     */
    protected static array $baseConditions = [ ];

    /**
     * @param DateTime|null $from
     * @return Track[]|\Traversable
     */
    public function getRecentTracks(?DateTime $from = null)
    {
        if (!$from) {
            $from = new DateTime();

            $from->modify('-3 years first day of january');
        }

        $tracks = $this->findBy(
            [ 'indexed_date' => [ '$gte' => new UTCDateTime($from->getTimestamp() * 1000) ] ],
            [ 'sort' => [ 'indexed_date' => -1 ] ]
        );

        return $tracks;
    }
}
