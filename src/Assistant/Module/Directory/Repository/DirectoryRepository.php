<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Repository\AbstractObjectRepository;
use Assistant\Module\Directory\Model\Directory;

/**
 * Repozytorium obiektów Directory
 */
class DirectoryRepository extends AbstractObjectRepository
{
    /**
     * {@inheritDoc}
     */
    protected const COLLECTION = 'directories';

    /**
     * {@inheritDoc}
     */
    protected const MODEL = Directory::class;

    /**
     * {@inheritDoc}
     */
    protected static array $baseConditions = [ 'ignored' => false ];
}
