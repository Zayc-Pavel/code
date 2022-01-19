<?php

namespace App\Service\Report;

use App\Domain\Report\Repository\ReportBRepository;
use App\Domain\Report\Repository\ReportRepository;
use App\Entity\Currency;
use App\Entity\Exchange;
use App\Entity\ReportB;
use App\Entity\User;
use App\Service\Exchange\CalculationHelper;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class ReportBService
 * @package App\Service\Report
 */
class ReportBService
{
    /**
     * @var ReportRepository
     */
    private $reportRepository;

    /**
     * @var ObjectManager
     */
    private $manager;
    /**
     * @var ReportBRepository
     */
    private $reportBRepository;

    /**
     * ReportBService constructor.
     * @param ReportRepository $reportRepository
     * @param ObjectManager $manager
     * @param ReportBRepository $reportBRepository
     */
    public function __construct(ReportRepository $reportRepository, ObjectManager $manager,
                                ReportBRepository $reportBRepository)
    {
        $this->reportRepository = $reportRepository;
        $this->manager = $manager;
        $this->reportBRepository = $reportBRepository;
    }

    /**
     * @param User $user
     * @param Exchange $exchange
     * @param \DateTime $dateTime
     * @return ReportB
     */
    public function generate(User $user, Exchange $exchange, \DateTime $dateTime): ReportB
    {
        $lastReport = $this->reportRepository->findByDate($user, $exchange, $dateTime);
        $lastBTCReport = $this->reportRepository->findLastByCurrency($user, $exchange, $dateTime, Currency::CODE_BTC);

        $euroStock = $this->reportRepository->getEuroStockByDate($user, $exchange, $dateTime);

        $btcRateUPL = CalculationHelper::mul($lastBTCReport->getFifoAKCrypto(), $lastBTCReport->getRateReal());
        $btcUPL = CalculationHelper::sub($btcRateUPL, $lastBTCReport->getFifoAKEuroSum());

        $lastReportRow = $this->reportBRepository->getLastByUser($user, $exchange);

        $number = $lastReportRow->getNumber() + 1;
        $reportB = (new ReportB)
            ->setUser($user)
            ->setDateTime($dateTime)
            ->setNumber($number)
            ->setExchange($exchange)
            ->setEuroStock($euroStock)
            ->setEuroRPL($lastReport->getFifoRPLSum())
            ->setBtcStock($lastBTCReport->getFifoAKCrypto())
            ->setBtcUPL($btcUPL)
            ->setFifoAkSum($lastBTCReport->getFifoAKEuroSum())
            ->setRate($lastBTCReport->getRateReal());

        $this->manager->persist($reportB);

        $this->manager->flush();

        return $reportB;
    }

    public function truncateReport(): void
    {
        $this->reportBRepository->truncate();
    }

    /**
     * @param User $user
     * @param \DateTime|null $dateTime
     */
    public function truncateReportByUser(User $user, ?\DateTime $dateTime): void
    {
        $this->reportBRepository->truncateByUser($user, $dateTime);
    }
}