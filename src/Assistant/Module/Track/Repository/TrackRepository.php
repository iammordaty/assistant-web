<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Repository as BaseRepository;

class TrackRepository extends BaseRepository
{
    /**
     * {@inheritDoc}
     */
    protected static $collection = 'tracks';

    /**
     * {@inheritDoc}
     */
    protected static $model = 'Assistant\Module\Track\Model\Track';

    /**
     * {@inheritDoc}
     */
    protected static $baseConditions = [ 'ignored' => false ];
}
