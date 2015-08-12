<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Repository\AbstractObjectRepository;

/**
 * Repozytorium obiektów Directory
 */
class DirectoryRepository extends AbstractObjectRepository
{
    /**
     * {@inheritDoc}
     */
    protected static $collection = 'directories';

    /**
     * {@inheritDoc}
     */
    protected static $model = 'Assistant\Module\Directory\Model\Directory';

    /**
     * {@inheritDoc}
     */
    protected static $baseConditions = [ 'ignored' => false ];
}
