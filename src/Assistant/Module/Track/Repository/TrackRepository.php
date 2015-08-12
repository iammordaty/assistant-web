<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Repository\Repository;

class TrackRepository extends Repository
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
