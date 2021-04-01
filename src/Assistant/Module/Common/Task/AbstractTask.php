<?php

namespace Assistant\Module\Common\Task;

use Monolog\Logger;
use Slim\Slim;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Monolog\Processor\MemoryUsageProcessor;

abstract class AbstractTask extends Command
{
    /**
     * Obiekt klasy Slim
     *
     * @var Slim
     */
    protected $app;

    /**
     * Obiekt klasy Logger
     *
     * @var Logger
     */
    protected $log;

    /**
     * Obiekt InputInterface
     *
     * @var InputInterface
     */
    protected $input;

    /**
     * Obiekt OutputInterface
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Konstruktor
     *
     * @param Slim $app
     * @param string $name
     */
    public function __construct(Slim $app, $name = null)
    {
        $this->app = $app;

        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $procId = uniqid('procId', true);

        $this->app->container[Logger::class]
            ->pushProcessor(new MemoryUsageProcessor())
            ->pushProcessor(function ($record) use ($procId) {
                $record['extra']['task'] = $this->getName();
                $record['extra']['procId'] = $procId;

                return $record;
            });

        unset($procId);
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
