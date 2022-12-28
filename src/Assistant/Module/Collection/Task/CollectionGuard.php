<?php

namespace Assistant\Module\Collection\Task;

use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class CollectionGuard
{
    public function __construct(
        private readonly TrackService $trackService,
        private readonly QuestionHelper $questionHelper,
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {
    }

    public function __invoke(IncomingTrack|Track $track): void
    {
        if ($this->trackService->getLocationArbiter()->isInIncoming($track->getFile())) {
            return;
        }

        $question = new ConfirmationQuestion(
            "\nHead's up! The song is in a collection. Continue? ",
            default: false
        );

        if (!$this->questionHelper->ask($this->input, $this->output, $question)) {
            throw new \RuntimeException('Ok, aborting...');
        }
    }
}
