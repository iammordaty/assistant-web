<?php

namespace Assistant\Module\Collection\Extension\Autocomplete;

use Assistant\Module\Common\Repository\MetadataFieldAutocompleteRepository;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;

final readonly class MetadataFieldAutocompleteService
{
    private const ALLOWED_TYPES = [
        'album',
        'artist',
        'genre',
        'publisher',
    ];

    public function __construct(private MetadataFieldAutocompleteRepository $repository)
    {
    }

    public function __invoke(string $query, string $type, ?int $limit = null): array
    {
        $this->validate($query, $type, $limit);

        $entries = $this->repository->get($query, $type, $limit ?: PHP_INT_MAX);

        return $entries;
    }

    private function validate(string $query, string $type, ?int $limit): void
    {
        // Docelowo typ może być opcjonalny, bo — kto wie — może zaistnieje potrzeba takiego autocompletera,
        // który będzie szukać po wszystkich polach dostępnych w bazie (np., w przyszłości tagi essentii),
        // na zasadzie skojarzeń

        if (!$query) {
            throw new InvalidArgumentException(
                'Parameter "query" is required',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (strlen($query) < 2) {
            throw new InvalidArgumentException(
                'Parameter "query" must be at least 2 characters long',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (!in_array($type, self::ALLOWED_TYPES)) {
            $message = sprintf(
                'Parameter "type" has invalid value (%s). Allowed types are: %s.',
                $type,
                implode(', ', self::ALLOWED_TYPES)
            );

            throw new InvalidArgumentException($message, StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        if ($limit && (!is_numeric($limit) || (int) $limit <= 0)) {
            throw new InvalidArgumentException(
                'Parameter "type" has invalid value (%d)',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }
}
