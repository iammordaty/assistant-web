<?php

namespace Assistant\Module\Common\Task;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Monolog\Processor\MemoryUsageProcessor;

abstract class AbstractTask extends Command
{
    /**
     * Obiekt klasy Slim
     *
     * @var \Slim\Slim
     */
    protected $app;

    /**
     * Obiekt klasy Logger
     *
     * @var \Monolog\Logger
     */
    protected $log;

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
     * Konstruktor
     *
     * @param \Slim\Slim $app
     * @param string $name
     */
    public function __construct(\Slim\Slim $app, $name = null)
    {
        $this->app = $app;

        parent::__construct($name);

        $this->setup();
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * Przygotowuje task do użycia
     */
    private function setup()
    {
        $procId = uniqid();

        $this->app->log
            ->pushProcessor(new MemoryUsageProcessor())
            ->pushProcessor(function ($record) use ($procId) {
                $record['extra']['task'] = $this->getName();
                $record['extra']['procId'] = $procId;

                return $record;
            });

        return;
    }
}
