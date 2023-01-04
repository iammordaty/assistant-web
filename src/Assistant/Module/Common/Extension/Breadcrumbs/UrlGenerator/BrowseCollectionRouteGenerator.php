<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;
use Assistant\Module\Common\Extension\Route;

final class BrowseCollectionRouteGenerator
{
    public function __invoke(Breadcrumb $breadcrumb): Route
    {
        return Route::create(
            'directory.browse.index',
            [ 'guid' => $breadcrumb->guid ]
        );
    }
}
