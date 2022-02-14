<?php

namespace App\Command;

use App\Service\Configuration\ConfigurationManager;
use App\Service\Rating\RatingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RatingCommand
 * @package App\Command
 */
class RatingCommand extends Command {

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var \App\Service\Rating\RatingService
     */
    private $ratingService;

    /**
     * RatingCommand constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \App\Service\Rating\RatingService $ratingService
     */
    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, RatingService $ratingService) {
        parent::__construct();

        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->ratingService = $ratingService;
    }

    protected function configure() {
        $this
            ->setName('rating')
            ->setDescription('Command for running calculate ratings for previous week');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln(['Calculate rating started']);

        $startWeek = new \DateTime("last week monday");
        $endWeek = (new \DateTime("last week sunday"))->setTime(23, 59, 59);
        $output->writeln(["Calculate rating from {$startWeek->format(ConfigurationManager::DATE_FORMAT)} to {$endWeek->format(ConfigurationManager::DATE_FORMAT)}"]);

        $this->ratingService->updateMetricUserLogins($startWeek, $endWeek);
        $this->ratingService->updateMetricProviderSearch($startWeek, $endWeek);
        $this->ratingService->updateFeedbackRating($startWeek, $endWeek);
        $this->ratingService->updateMetricUploadedProjects($startWeek, $endWeek);
        $this->ratingService->updateMetricMainUploadedProjects($startWeek, $endWeek);
        $this->ratingService->updateMetricApprovedProject($startWeek, $endWeek);
        $this->ratingService->updateRecordCompletionRating($startWeek, $endWeek);
        $this->ratingService->updateTotalRating();

        $output->writeln(['Calculate rating finished']);
    }
}