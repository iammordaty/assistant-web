<?php

use Assistant\Module\Dashboard\Controller\DashboardController;
use Slim\App;

return static function (App $app): void {
    $app
        ->get('/', [ DashboardController::class, 'index' ])
        ->setName('dashboard');
};
