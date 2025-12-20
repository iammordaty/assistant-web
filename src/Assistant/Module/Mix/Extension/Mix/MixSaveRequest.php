<?php

namespace Assistant\Module\Mix\Extension\Mix;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Slim\Http\ServerRequest;

final readonly class MixSaveRequest
{
    private function __construct(
        public string $name,
        public ?string $description,
        public ?string $comment,
        public DateTimeImmutable $created,
        public ?DateTimeImmutable $modified,
        public ?DateTimeImmutable $performed,
    ) {
    }

    public static function create(ServerRequest $request): self
    {
        $body = $request->getParsedBody();

        $name = $body['name'] ?? null;

        if (!$name) {
            throw new InvalidArgumentException(
                'Parameter "name" is required.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $description = $body['description'] ?? null;

        $comment = $body['comment'] ?? null;

        $created = $body['created'] ?? new DateTimeImmutable();

        if (is_string($created)) {
            $created = new DateTimeImmutable($created);
        }

        $modified = $body['modified'] ?? null;

        if (is_string($modified)) {
            $modified = new DateTimeImmutable($modified);
        }

        $performed = $body['performed'] ?? null;

        if (is_string($performed)) {
            $performed = new DateTimeImmutable($performed);
        }

        return new self(
            trim($name),
            trim($description),
            trim($comment),
            $created,
            $modified,
            $performed,
        );
    }
} 
