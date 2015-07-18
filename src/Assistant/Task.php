<?php

namespace Assistant;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Task extends Command
{
    /**
     * Obiekt InputInterface
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * Obiekt OutputInterface
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Obiekt klasy Slim
     *
     * @var \Slim\Slim
     */
    protected $app;

    /**
     * Konstruktor
     *
     * @param \Slim\Slim $app
     * @param string $name
     */
    public function __construct(\Slim\Slim $app, $name = null)
    {
        $this->app = $app;

        parent::__construct($name);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * info
     *
     * @param string $message
     * @param bool $newline
     */
    protected function info($message, $newline = true)
    {
        $this->output->write(
            sprintf('<info>%s</info>', $message),
            $newline
        );
    }

    /**
     * error
     *
     * @param string $message
     * @param bool $newline
     */
    protected function error($message, $newline = true)
    {
        $this->output->write(
            sprintf('<error>%s</error>', $message),
            $newline
        );
    }

    /**
     * comment
     *
     * @param string $message
     * @param bool $newline
     */
    protected function comment($message, $newline = true)
    {
        $this->output->write(
            sprintf('<comment>%s</comment>', $message),
            $newline
        );
    }
}
