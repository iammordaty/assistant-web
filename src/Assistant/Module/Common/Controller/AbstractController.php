<?php

namespace Assistant\Module\Common\Controller;

/**
 * Abstrakcyjna klasa dla kontrolerów
 */
abstract class AbstractController
{
    /**
     * @var \Slim\Slim
     */
    protected $app;

    /**
     * Konstruktor
     *
     * @param \Slim\Slim $app
     */
    public function __construct(\Slim\Slim $app)
    {
        $this->app = $app;
    }
}
