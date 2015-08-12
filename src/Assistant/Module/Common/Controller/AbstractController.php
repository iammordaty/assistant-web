<?php

namespace Assistant\Module\Common\Controller;

abstract class AbstractController
{
    /**
     * @var \Slim\Slim
     */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }
}
