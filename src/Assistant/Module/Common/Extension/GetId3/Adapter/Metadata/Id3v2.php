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
            if (in_array($field, $this->fields) && !empty($value[0]) ) {
                switch ($field) {
                    case 'track_number':
                    case 'year':
                        $metadata[$field] = (int) $value[0];
                        break;

                    case 'bpm':
                        $metadata[$field] = (float) $value[0];
                        break;

                    default:
                        $metadata[$field] = $value[0];
                        break;
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

        // duplikaty oraz niektóre wielkrotne pola są niedozwolone
        foreach ($rawId3v2Info as $field => $value) {
            $rawId3v2Info[$field] = [ reset($value) ];
        }

        foreach ($metadata as $field => $value) {
            if (in_array($field, $this->fields)) {
                $rawId3v2Info[$field] = [ $value ];
            }
        }

        $this->rawInfo['tags']['id3v2'] = $rawId3v2Info;

        return $rawId3v2Info;
    }
}
