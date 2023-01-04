<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;
use Assistant\Module\Common\Extension\Route;

final class BrowseIncomingRouteGenerator
{
    public function __invoke(Breadcrumb $breadcrumb): Route
    {
        return Route::create(
            'directory.browse.incoming',
            [ 'pathname' => $breadcrumb->pathname ]
        );
    }
}
