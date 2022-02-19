<?php

namespace Assistant\Module\Collection\Task\Indexer;

enum IndexingDateStrategy: string
{
    case CURRENT_DATE = 'current';
    case FIXED_DATE = 'fixed';
    case FROM_PARENT_PATHNAME = 'pathname';
}
