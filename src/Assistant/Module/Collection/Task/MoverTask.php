<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Common\Task\AbstractTask;
use Monolog\Logger;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Task przenoszący gotowe (otagowane) utwory do odpowiednich katalogów
 */
class MoverTask extends AbstractTask
{
    /**
     * Tablica asocjacyjna zawierająca statystyki zadania
     *
     * @var array
     */
    private array $stats;

    protected function configure(): void
    {
        $this
            ->setName('collection:move')
            ->setDescription('Move new and tagged tracks to target directories')
            ->addArgument(
                'pathname',
                InputArgument::REQUIRED,
                'Pathname to move'
            )->addArgument(
                'targetPathname',
                InputArgument::REQUIRED,
                'Target pathname'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): int
    {
        parent::initialize($input, $output);

        $this->stats = [ ];
    }

    /**
     * Rozpoczyna proces usuwania przenoszenia podanego elementu
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->app->container[Logger::class]->info('Task executed', array_merge($input->getArguments(), $input->getOptions()));

        $element = new SplFileInfo($input->getArgument('pathname'));

        if (file_exists($element->getPathname()) === false) {
            throw new \RuntimeException("Element {$element->getPathname()} does not exists!");
        }

        $target = new SplFileInfo($input->getArgument('targetPathname'));

        if ($target->isFile() === true && file_exists($target->getPathname()) === true) {
            throw new \RuntimeException("Target {$target->getPathname()} already exists!");
        }

        if (file_exists($target->getPath()) === false && !mkdir($concurrentDirectory = $target->getPath(), 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException("Can\'t create directory {$target->getPath()}.");
        }

        if (rename($element->getPathname(), $target->getPathname()) === false) {
            throw new \RuntimeException("Can\'t move {$element->getPathname()} to {$target->getPathname()}.");
        }

        $this->app->container[Logger::class]->info('Task finished', $this->stats);

        return AbstractTask::SUCCESS;
    }
}
