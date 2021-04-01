<?php

namespace Assistant\Module\Collection\Extension\Reader;

use SplFileInfo;

interface ReaderInterface
{
    public function read(SplFileInfo $node);
}
