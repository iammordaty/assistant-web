<?php

namespace Assistant\Module\Collection\Extension\Analysis;

use MongoDB\Model\BSONDocument;

final readonly class AnalysisIssue
{
    public string $hash;

    public function __construct(
        public AnalysisCategory $category,
        public string $type,
        public array $details,
        public bool $ignored = false,
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
        $details = match (true) {
            $data['details'] instanceof BSONDocument => $data['details']->getArrayCopy(),
            $data['details'] instanceof \stdClass => (array) $data['details'],
            default => $data['details'],
        };

        return new self(
            AnalysisCategory::from($data['category']),
            $data['type'],
            $details,
            (bool) ($data['ignored'] ?? false),
        );
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
