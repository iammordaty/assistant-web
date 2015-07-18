<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Repository as BaseRepository;

class DirectoryRepository extends BaseRepository
{
    protected static $collection = 'directories';
    protected static $model = 'Assistant\Module\Directory\Model\Directory';
    protected static $baseConditions = [ 'ignored' => false ];
}
