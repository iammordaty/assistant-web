<?php

namespace Assistant\Module\Common\Extension\Breadcrumbs\UrlGenerator;

use Assistant\Module\Common\Extension\Breadcrumbs\Breadcrumb;

final class EmptyRouteGenerator
{
    public function __invoke(Breadcrumb $breadcrumb): null
    {
        return null;
    }
}
