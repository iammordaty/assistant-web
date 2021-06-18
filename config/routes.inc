<?php

use Slim\App;
use Slim\Handlers\Strategies\RequestResponse;

return static function (App $app): void {
    $app->getRouteCollector()->setDefaultInvocationStrategy(new RequestResponse());

    (require_once BASE_DIR . '/src/Assistant/Module/Common/Resources/routes/default.inc')($app);
    (require_once BASE_DIR . '/src/Assistant/Module/Dashboard/Resources/routes/default.inc')($app);
    (require_once BASE_DIR . '/src/Assistant/Module/Directory/Resources/routes/default.inc')($app);
    (require_once BASE_DIR . '/src/Assistant/Module/Mix/Resources/routes/default.inc')($app);
    (require_once BASE_DIR . '/src/Assistant/Module/Search/Resources/routes/default.inc')($app);
    (require_once BASE_DIR . '/src/Assistant/Module/Track/Resources/routes/default.inc')($app);
};
