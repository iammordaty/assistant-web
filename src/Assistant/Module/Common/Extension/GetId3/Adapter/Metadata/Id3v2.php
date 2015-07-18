<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter\Metadata;

use Assistant\Module\Common\Extension\GetId3\Adapter\Metadata as BaseMetadata;

class Id3v2 extends BaseMetadata
{
    /**
     * @var array
     */
    private $fields = [
        'artist',
        'title',
        'album',
        'track_number',
        'year',
        'genre',
        'bpm',
        'initial_key'
    ];

    public function getMetadata()
    {
        if (isset($this->rawInfo['tags']['id3v2']) === false) {
            return null;
        }

        $metadata = [ ];

        foreach ($this->rawInfo['tags']['id3v2'] as $field => $value) {
            if (in_array($field, $this->fields)) {
                $metadata[$field] = $value[0];

                if (is_numeric($metadata[$field])) {
                    $metadata[$field] = (int) $metadata[$field];
                }
            }
        }

        return $metadata;
    }
}
