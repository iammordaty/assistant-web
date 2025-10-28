<?php

namespace Assistant\Module\Mix\Extension\Mix;

use InvalidArgumentException;
use Slim\Http\ServerRequest;

final class MixSaveRequest
{
    public function __construct(
        public string $name,
        public string $description,
        public ?string $comment,
        public ?string $created,
        public ?string $modified,
        public ?string $performed,
    ) {
    }

    public static function create(ServerRequest $request): self
    {
        $postData = $request->getParsedBody();
        
        if (!is_array($postData)) {
            throw new InvalidArgumentException('Request body must be an array');
        }

        if (!isset($postData['name'])) {
            throw new InvalidArgumentException('Mix name is required');
        }
        
        $name = $postData['name'];
        if (!is_string($name) || empty(trim($name))) {
            throw new InvalidArgumentException('Mix name must be a non-empty string');
        }

        if (!isset($postData['description'])) {
            throw new InvalidArgumentException('Mix description is required');
        }
        
        $description = $postData['description'];
        if (!is_string($description)) {
            throw new InvalidArgumentException('Mix description must be a string');
        }

        $comment = $postData['comment'] ?? null;
        if ($comment !== null && !is_string($comment)) {
            throw new InvalidArgumentException('Mix comment must be a string or null');
        }

        $created = $postData['created'] ?? null;
        if ($created !== null && (!is_string($created) || empty($created))) {
            throw new InvalidArgumentException('Created date must be a non-empty string or null');
        }

        $modified = $postData['modified'] ?? null;
        if ($modified !== null && (!is_string($modified) || empty($modified))) {
            throw new InvalidArgumentException('Modified date must be a non-empty string or null');
        }

        $performed = $postData['performed'] ?? null;
        if ($performed !== null && (!is_string($performed) || empty($performed))) {
            throw new InvalidArgumentException('Performed date must be a non-empty string or null');
        }

        return new self(
            trim($name),
            $description,
            $comment,
            $created,
            $modified,
            $performed
        );
    }
} 
