<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

use SplFileInfo;

final class MusicClassifierResultFileMalformedException extends MusicClassifierException
{
    public function __construct(SplFileInfo $resultFile, string $message)
    {
        $message = sprintf(
            'Result file "%s" is malformed (not valid JSON file): %s', 
            $resultFile->getFilename(), 
            $message
        );

        parent::__construct($message);
    }
}
