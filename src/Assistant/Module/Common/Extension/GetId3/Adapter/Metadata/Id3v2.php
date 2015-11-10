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

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function prepareMetadata(array $metadata)
    {
        $rawId3v2Info = isset($this->rawInfo['tags']['id3v2']) ? $this->rawInfo['tags']['id3v2'] : [ ];

        foreach ($metadata as $field => $value) {
            if (in_array($field, $this->fields)) {
                $rawId3v2Info[$field] = [ $value ];
            }
        }

        $this->rawInfo['tags']['id3v2'] = $rawId3v2Info;

        return $rawId3v2Info;
    }
}
