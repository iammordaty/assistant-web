<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

final readonly class AnalysisIssue
{
    public string $hash;

    public function __construct(
        public AnalysisCategory $category,
        public string $type,
        public array $details,
        public bool $ignored = false,
        public ?string $mongoId = null,
    ) {
        $this->hash = self::computeHash($category, $type, $details);
    }

    public function toStorage(): array
    {
        return [
            'record_type' => 'issue',
            'hash' => $this->hash,
            'category' => $this->category->value,
            'type' => $this->type,
            'details' => $this->details,
            'ignored' => $this->ignored,
        ];
    }

    public static function fromStorage(array $data): self
    {
        $details = self::convertBsonToArray($data['details'] ?? []);
        $mongoId = isset($data['_id']) ? (string) $data['_id'] : null;

        return new self(
            AnalysisCategory::from($data['category']),
            $data['type'],
            $details,
            (bool) ($data['ignored'] ?? false),
            $mongoId,
        );
    }

    private static function convertBsonToArray(mixed $value): mixed
    {
        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            $value = $value->getArrayCopy();
        }

        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            return array_map(self::convertBsonToArray(...), $value);
        }

        return $value;
    }

    public function toRawArray(): array
    {
        return [
            '_id' => $this->mongoId,
            'hash' => $this->hash,
            'category' => $this->category->value,
            'type' => $this->type,
            'details' => $this->details,
            'ignored' => $this->ignored,
        ];
    }

    private static function computeHash(AnalysisCategory $category, string $type, array $details): string
    {
        $distinguishingKeys = ['pathname', 'value_a', 'value_b', 'guid', 'guid_base', 'field'];

        $hashData = [$category->value, $type];

        foreach ($distinguishingKeys as $key) {
            if (isset($details[$key])) {
                $hashData[] = is_array($details[$key])
                    ? implode(',', $details[$key])
                    : (string) $details[$key];
            }
        }

        return md5(implode('|', $hashData));
    }
}
