<?php

namespace Assistant\Module\Common\Extension\Slim\Views;

class Twig extends \Slim\View
{
    /**
     * http://www.twig-project.org/book/03-Twig-for-Developers
     *
     * @var array The options for the Twig environment, see
     */
    public $parserOptions = [];

    /**
     * @var \Twig_Extension The Twig extensions you want to load
     */
    public $parserExtensions = [];

    /**
     * @var array
     */
    public $loaderPathNamespaces = [];

    /**
     * @var \Twig_Environment The Twig environment for rendering templates.
     */
    private $parserInstance = null;

    /**
     * Render Twig Template
     *
     * This method will output the rendered template content
     *
     * @param string $template The path to the Twig template, relative to the Twig templates directory.
     * @param null $data
     * @return string
     */
    public function render($template, $data = null)
    {
        $env = $this->getInstance();
        $parser = $env->loadTemplate($template);

        $merged = array_merge($this->all(), (array) $data);

        return $parser->render($merged);
    }

    /**
     * Creates new TwigEnvironment if it doesn't already exist, and returns it.
     *
     * @return \Twig_Environment
     */
    public function getInstance()
    {
        if ($this->parserInstance === null) {
            $loader = new \Twig_Loader_Filesystem();

            foreach ($this->loaderPathNamespaces as $namespace => $path) {
                $loader->addPath($path, $namespace);
            }

            $this->parserInstance = new \Twig_Environment(
                $loader,
                $this->parserOptions
            );

            foreach ($this->parserExtensions as $ext) {
                $extension = is_object($ext) ? $ext : new $ext;
                $this->parserInstance->addExtension($extension);
            }
        }

        return $this->parserInstance;
    }
}
