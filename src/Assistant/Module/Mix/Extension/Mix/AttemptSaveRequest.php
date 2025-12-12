<?php

namespace Assistant\Module\Mix\Extension\Mix;

use DateTime;
use Slim\Http\ServerRequest;

final class AttemptSaveRequest
{
    private function __construct(
        public ?string $id,
        public DateTime $created,
        public ?string $comment,
        public array $trackList,
    ) {
    }

    public static function create(ServerRequest $request): self
    {
        $body = $request->getParsedBody();

        $id = $body['id'] ?? null;

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

        $comment = $body['comment'] ?? null;

        $trackList = $body['trackList'] ?? [];

        return new self($id, $created, $comment, $trackList);
    }
}
