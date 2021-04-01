<?php

namespace Assistant\Module\Common\Controller;

use Slim\Slim;

/**
 * Abstrakcyjna klasa dla kontrolerów
 */
abstract class AbstractController
{
    /**
     * @var Slim
     */
    protected $app;

    /**
     * Konstruktor
     *
     * @param Slim $app
     */
    public function __construct(Slim $app)
    {
        $this->app = $app;
    }
}
