<?php

namespace Assistant\Module\Directory\Repository;

use Assistant\Module\Common\Repository\AbstractObjectRepository;

class DirectoryRepository extends AbstractObjectRepository
{
    protected static $collection = 'directories';
    protected static $model = 'Assistant\Module\Directory\Model\Directory';
    protected static $baseConditions = [ 'ignored' => false ];
}
