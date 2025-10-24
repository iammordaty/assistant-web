<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter\Metadata;

use Assistant\Module\Common\Extension\GetId3\Adapter\MetadataAdapterInterface;
use Assistant\Module\Track\Extension\TrackMetadataFields;

final class Id3v2 implements MetadataAdapterInterface
{
    private array $rawInfo = [];

    public function setRawInfo(array $rawInfo): self
    {
        $this->rawInfo = $rawInfo;

        return $this;
    }

    /** {@inheritDoc} */
    public function getMetadata(): array
    {
        $rawId3v2Info = $this->rawInfo['tags']['id3v2'] ?? [];

        if (!isset($rawId3v2Info)) {
            return [];
        }

        $metadata = [ ];

        foreach ($rawId3v2Info as $field => $value) {
            $firstValue = $value[0] ?? null;

            if (!TrackMetadataFields::isSupportedMetadataField($field) || !$firstValue) {
                continue;
            }

            $metadata[$field] = match ($field) {
                TrackMetadataFields::TRACK_NUMBER, TrackMetadataFields::YEAR => (int) $firstValue,
                TrackMetadataFields::BPM => (float) $firstValue,
                default => $firstValue,
            };
        }

        return $metadata;
    }

    /** {@inheritDoc} */
    public function prepareMetadata(array $metadata): array
    {
        $rawId3v2Info = $this->rawInfo['tags']['id3v2'] ?? [];

        // duplikaty oraz wielokrotne tagi i wartości są niedozwolone
        foreach ($rawId3v2Info as $field => $value) {
            $firstValue = reset($value);

            $rawId3v2Info[$field] = [ (string) $firstValue ];
        }

        foreach ($metadata as $field => $value) {
            if (TrackMetadataFields::isSupportedMetadataField($field)) {
                $rawId3v2Info[$field] = [ (string) $value ];
            }
        }

        $this->rawInfo['tags']['id3v2'] = $rawId3v2Info;

        return $rawId3v2Info;
    }
}
