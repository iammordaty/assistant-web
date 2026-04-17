<?php

use Assistant\Module\Collection\Controller\CollectionStatusController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/collection', function (RouteCollectorProxy $group) {
        $group
            ->get('/status', [ CollectionStatusController::class, 'index' ])
            ->setName('collection.status.index');

        $group
            ->post('/status/toggle-ignore/{hash}', [ CollectionStatusController::class, 'toggleIgnore' ])
            ->setName('collection.status.toggle-ignore');

        $group
            ->get('/status/cross-reference', [ CollectionStatusController::class, 'crossReference' ])
            ->setName('collection.status.cross-reference');

        $group
            ->get('/status/filename-mismatch', [ CollectionStatusController::class, 'filenameMismatch' ])
            ->setName('collection.status.filename-mismatch');

        $group
            ->get('/status/empty-metadata', [ CollectionStatusController::class, 'emptyMetadata' ])
            ->setName('collection.status.empty-metadata');

        $group
            ->get('/status/low-audio-quality', [ CollectionStatusController::class, 'lowAudioQuality' ])
            ->setName('collection.status.low-audio-quality');

        $group
            ->get('/status/similar-artist', [ CollectionStatusController::class, 'similarArtist' ])
            ->setName('collection.status.similar-artist');

        $group
            ->get('/status/similar-publisher', [ CollectionStatusController::class, 'similarPublisher' ])
            ->setName('collection.status.similar-publisher');

        $group
            ->get('/status/similar-genre', [ CollectionStatusController::class, 'similarGenre' ])
            ->setName('collection.status.similar-genre');

        $group
            ->get('/status/suspicious-year', [ CollectionStatusController::class, 'suspiciousYear' ])
            ->setName('collection.status.suspicious-year');

        $group
            ->get('/status/rare-genre', [ CollectionStatusController::class, 'rareGenre' ])
            ->setName('collection.status.rare-genre');

        $group
            ->get('/status/rare-key', [ CollectionStatusController::class, 'rareKey' ])
            ->setName('collection.status.rare-key');

        $group
            ->get('/status/potential-duplicate', [ CollectionStatusController::class, 'potentialDuplicate' ])
            ->setName('collection.status.potential-duplicate');
    });
};
