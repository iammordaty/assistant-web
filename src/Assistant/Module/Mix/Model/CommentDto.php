<?php

namespace Assistant\Module\Mix\Model;

use MongoDB\Model\BSONDocument;

final class CommentDto
{
    public function __construct(
        public ?int $time,
        public ?string $type,
        public string $content,
    ) {}

    public static function fromStorage(BSONDocument $data): self
    {
        return new self(
            (int) $data->time,
            $data->type,
            $data->content,
        );
    }

    public static function fromModel(Comment $comment): self
    {
        return new self(
            $comment->time,
            $comment->type,
            $comment->content
        );
    }

    public static function fromJson(array $comment): self
    {
        return new self(
            $comment['time'],
            $comment['type'],
            $comment['content']
        );
    }

    public function toStorage(): array
    {
        return [
            'time' => $this->time,
            'type' => $this->type,
            'content' => $this->content,
        ];
    }

    public function toJson(): array
    {
        return [
            'time' => $this->time,
            'type' => $this->type,
            'content' => $this->content,
        ];
    }
}
