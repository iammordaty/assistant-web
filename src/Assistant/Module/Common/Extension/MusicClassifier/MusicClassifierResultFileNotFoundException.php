<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use SplFileInfo;

final class MusicClassifierResultFileNotFoundException extends MusicClassifierException
{
    public function __construct(SplFileInfo $resultFile)
    {
        $message = sprintf('Result file "%s" does not exists or is not readable', $resultFile->getFilename());

        parent::__construct($message);
    }
}
