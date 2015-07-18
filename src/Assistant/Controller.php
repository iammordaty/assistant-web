<?php

namespace Assistant;

abstract class Controller
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
