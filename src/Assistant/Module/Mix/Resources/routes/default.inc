<?php

use Assistant\Module\Mix;
use Assistant\Module\Mix\Controller\MixController;
use Slim\App;

return static function (App $app): void {
    $app
        ->map(['GET', 'POST'], '/mix', [ MixController::class, 'index' ])
        ->setName('mix.mix.index');
};
