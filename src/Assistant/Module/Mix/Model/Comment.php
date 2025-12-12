<?php

namespace Assistant\Module\Mix\Model;

final readonly class Comment
{
    public function __construct(
        public ?int $time,
        public ?string $type,
        public string $content,
    ) {}

    public static function fromDto(CommentDto $dto): self
    {
        return new self(
            $dto->time,
            $dto->type,
            $dto->content
        );
    }
}
