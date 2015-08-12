<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Repository\Repository;

class DirectoryRepository extends Repository
{
    protected static $collection = 'directories';
    protected static $model = 'Assistant\Module\Directory\Model\Directory';
    protected static $baseConditions = [ 'ignored' => false ];
}
