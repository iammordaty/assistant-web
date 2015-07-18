<?php

namespace Assistant\Module\Track\Repository;

use Assistant\Repository as BaseRepository;

class TrackRepository extends BaseRepository
{
    protected static $collection = 'tracks';
    protected static $model = 'Assistant\Module\Track\Model\Track';
    protected static $baseConditions = [ 'ignored' => false ];
}
