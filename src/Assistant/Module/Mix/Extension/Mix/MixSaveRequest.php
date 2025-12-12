<?php

namespace Assistant\Module\Mix\Extension\Mix;

use DateTime;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Slim\Http\ServerRequest;

final readonly class MixSaveRequest
{
    private function __construct(
        public string $name,
        public ?string $description,
        public ?string $comment,
        public DateTime $created,
        public ?DateTime $modified,
        public ?DateTime $performed,
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

        $created = $body['created'] ?? new DateTime();

        if (is_string($created)) {
            $created = new DateTime($created);

            /* Docelowo:
            $created = DateTime::createFromFormat(DateTime::ATOM, $created);

            if (!$created) {
                throw new InvalidArgumentException(
                    'Parameter "created" has an invalid value (must be a valid date in ISO8601 format)',
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
            */
        }

        $modified = $body['modified'] ?? null;

        if (is_string($modified)) {
            $modified = new DateTime($modified);

            /* Docelowo:
            $modified = DateTime::createFromFormat(DateTime::ATOM, $modified);

            if (!$modified) {
                throw new InvalidArgumentException(
                    'Parameter "modified" has an invalid value (must be a valid date in ISO8601 format)',
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
            */
        }

        $performed = $body['performed'] ?? null;

        if (is_string($performed)) {
            $performed = new DateTime($performed);

            /* Docelowo:
            $performed = DateTime::createFromFormat(DateTime::ATOM, $performed);

            if (!$performed) {
                throw new InvalidArgumentException(
                    'Parameter "performed" has an invalid value (must be a valid date in ISO8601 format)',
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
            */
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
