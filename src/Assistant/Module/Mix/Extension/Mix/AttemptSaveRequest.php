<?php

namespace Assistant\Module\Mix\Extension\Mix;

use DateTimeImmutable;
use Slim\Http\ServerRequest;

final class AttemptSaveRequest
{
    private function __construct(
        public ?string $id,
        public ?DateTimeImmutable $created,
        public ?string $comment,
        public ?array $trackList,
    ) {
    }

    public static function create(ServerRequest $request): self
    {
        $body = $request->getParsedBody();

        $id = $body['id'] ?? null;

        $created = $body['created'] ?? new DateTimeImmutable();

        if (is_string($created)) {
            $created = new DateTimeImmutable($created);
        }

        $comment = $body['comment'] ?? null;

        $trackList = $body['trackList'] ?? [];

        return new self($id, $created, $comment, $trackList);
    }
}
