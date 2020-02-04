<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Track\Model\Track;

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
    protected static array $baseConditions = [ 'ignored' => false ];
}
