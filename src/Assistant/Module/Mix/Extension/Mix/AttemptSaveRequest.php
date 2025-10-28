<?php

namespace Assistant\Module\Mix\Extension\Mix;

use InvalidArgumentException;
use Slim\Http\ServerRequest;

final class AttemptSaveRequest
{
    public function __construct(
        public int $number,
        public string $created,
        public ?string $comment,
        public array $trackList,
    ) {
    }

    public static function create(ServerRequest $request): self
    {
        $postData = $request->getParsedBody();
        
        if (!is_array($postData)) {
            throw new InvalidArgumentException('Request body must be an array');
        }

        if (!isset($postData['number'])) {
            throw new InvalidArgumentException('Attempt number is required');
        }
        
        $number = (int) $postData['number'];
        if ($number <= 0) {
            throw new InvalidArgumentException('Attempt number must be greater than 0');
        }

        if (!isset($postData['created'])) {
            throw new InvalidArgumentException('Created date is required');
        }
        
        $created = $postData['created'];
        if (!is_string($created) || empty($created)) {
            throw new InvalidArgumentException('Created date must be a non-empty string');
        }

        // Walidacja listy utworów
        if (!isset($postData['trackList'])) {
            throw new InvalidArgumentException('Track list is required');
        }
        
        $trackList = $postData['trackList'];
        if (!is_array($trackList)) {
            throw new InvalidArgumentException('Track list must be an array');
        }

        foreach ($trackList as $index => $trackEntry) {
            if (!is_array($trackEntry)) {
                throw new InvalidArgumentException("Track entry at index {$index} must be an array");
            }
            
            if (!isset($trackEntry['trackGuid'])) {
                throw new InvalidArgumentException("Track entry at index {$index} missing trackGuid");
            }
            
            if (!isset($trackEntry['comments']) || !is_array($trackEntry['comments'])) {
                throw new InvalidArgumentException("Track entry at index {$index} missing or invalid comments array");
            }
            
            // Walidacja komentarzy
            foreach ($trackEntry['comments'] as $commentIndex => $comment) {
                if (!is_array($comment)) {
                    throw new InvalidArgumentException("Comment at track {$index}, comment {$commentIndex} must be an array");
                }
                
                if (!isset($comment['content'])) {
                    throw new InvalidArgumentException("Comment at track {$index}, comment {$commentIndex} missing content");
                }
            }
        }

        return new self(
            $number,
            $created,
            $postData['comment'] ?? null,
            $trackList
        );
    }
} 
