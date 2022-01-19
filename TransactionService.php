<?php

namespace App\Service\Transaction;

use App\Domain\Exchange\Exception\IncorrectFeeException;
use App\Domain\Transaction\Repository\TransactionRepository;
use App\Domain\Transaction\Repository\TransactionTypeRepository;
use App\Entity\Account;
use App\Entity\Currency;
use App\Entity\Event;
use App\Entity\Exchange;
use App\Entity\Transaction;
use App\Entity\TransactionType;
use App\Entity\User;
use App\Http\EventListener\ErrorCode;
use App\Service\Event\EventService;
use App\Service\Exchange\CalculationHelper;
use App\Service\Report\ReportQueueService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TransactionService
 * @package App\Service\Transaction
 */
class TransactionService
{
    /**
     * @var TransactionRepository
     */
    private $transactionRepository;

    /**
     * @var EventService
     */
    private $eventService;

    /**
     * @var TransactionTypeRepository
     */
    private $transactionTypeRepository;
    /**
     * @var ReportQueueService
     */
    private $reportQueueService;

    /**
     * TransactionService constructor.
     * @param TransactionRepository $transactionRepository
     * @param EventService $eventService
     * @param TransactionTypeRepository $transactionTypeRepository
     * @param ReportQueueService $reportQueueService
     */
    public function __construct(TransactionRepository $transactionRepository, EventService $eventService,
                                TransactionTypeRepository $transactionTypeRepository, ReportQueueService $reportQueueService)
    {
        $this->transactionRepository = $transactionRepository;
        $this->eventService = $eventService;
        $this->transactionTypeRepository = $transactionTypeRepository;
        $this->reportQueueService = $reportQueueService;
    }

    /**
     * @param User $user
     * @param string $amount
     * @param \DateTime $date
     * @param Account $account
     * @return Transaction
     */
    public function createPayInTransaction(User $user, string $amount, \DateTime $date, Account $account): Transaction
    {
        $event = $this->eventService->createPayInEvent($user, $date);

        $transaction = (new Transaction())
            ->setAmount($amount)
            ->setDate($date)
            ->setReceiverAcc($account)
            ->setEvent($event)
            ->setRealAmount($amount)
            ->setFee(CalculationHelper::getFormattedNumber(0));

        $this->transactionRepository->add($transaction);
        $this->reportQueueService->add($event);

        return $transaction;
    }

    /**
     * @param User $user
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param Account $account
     * @return Transaction
     */
    public function createPayOutTransaction(User $user, \DateTime $date, string $amount, string $fee,
                                            string $realAmount, Account $account): Transaction
    {
        $event = $this->eventService->createPayOutEvent($user, $date);

        $transaction = (new Transaction())
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setSenderAcc($account)
            ->setEvent($event)
            ->setDate($date);

        $this->transactionRepository->add($transaction);
        $this->reportQueueService->add($event);

        return $transaction;
    }

    /**
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param Account $receiverAccount
     * @param Event $event
     * @param string $rateNominal
     * @param int|null $tradingVolume
     * @return Transaction
     */
    public function createBuyTransaction(\DateTime $date, string $amount, string $fee, string $realAmount,
                                         Account $receiverAccount, Event $event, string $rateNominal, ?int $tradingVolume): Transaction
    {
        $transactionType = $this->getTransactionType(TransactionType::TYPE_BUY);

        $transaction = (new Transaction())
            ->setDate($date)
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setReceiverAcc($receiverAccount)
            ->setTransactionType($transactionType)
            ->setEvent($event)
            ->setRateNominal($rateNominal)
            ->setTradingVolume($tradingVolume);

        $this->transactionRepository->add($transaction);

        return $transaction;
    }

    /**
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param Account $senderAccount
     * @param Event $event
     * @param string $rateNominal
     * @param int|null $tradingVolume
     * @return Transaction
     */
    public function createSellTransaction(\DateTime $date, string $amount, string $fee, string $realAmount,
                                         Account $senderAccount, Event $event, string $rateNominal, ?int $tradingVolume): Transaction
    {
        $transactionType = $this->getTransactionType(TransactionType::TYPE_SELL);

        $transaction = (new Transaction())
            ->setDate($date)
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setSenderAcc($senderAccount)
            ->setTransactionType($transactionType)
            ->setEvent($event)
            ->setRateNominal($rateNominal)
            ->setTradingVolume($tradingVolume);

        $this->transactionRepository->add($transaction);

        return $transaction;
    }

    /**
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param Account $receiveAccount
     * @param Event $event
     * @return Transaction
     */
    public function createReceiveTransferTransaction(\DateTime $date, string $amount, string  $fee, string $realAmount,
                                                     Account $receiveAccount, Event $event): Transaction
    {
        $transactionType = $this->getTransactionType(TransactionType::TYPE_RECEIVE);

        $transaction = (new Transaction())
            ->setDate($date)
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setReceiverAcc($receiveAccount)
            ->setTransactionType($transactionType)
            ->setEvent($event);

        $this->transactionRepository->add($transaction);

        return $transaction;
    }

    /**
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param Account $senderAccount
     * @param Event $event
     * @return Transaction
     */
    public function createSendTransferTransaction(\DateTime $date, string $amount, string $fee, string $realAmount,
                                                  Account $senderAccount, Event $event): Transaction
    {
        $transactionType = $this->getTransactionType(TransactionType::TYPE_SEND);

        $transaction = (new Transaction())
            ->setDate($date)
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setSenderAcc($senderAccount)
            ->setTransactionType($transactionType)
            ->setEvent($event);

        $this->transactionRepository->add($transaction);

        return $transaction;
    }

    /**
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     */
    public function checkAmount(string $amount, string $fee, string $realAmount): void
    {
        $fullAmount = CalculationHelper::sum($fee, $realAmount);

        if (CalculationHelper::comp($fullAmount, $amount) !== 0) {
            throw new IncorrectFeeException(ErrorCode::FEE_AND_REAL_AMOUNT_NOT_CORRECT);
        }
    }

    /**
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     */
    public function checkPayoutAmount(string $amount, string $fee, string $realAmount): void
    {
        $fullAmount = CalculationHelper::sum($fee, $amount);

        if (CalculationHelper::comp($fullAmount, $realAmount) !== 0) {
            throw new IncorrectFeeException(ErrorCode::FEE_AND_REAL_AMOUNT_NOT_CORRECT);
        }
    }

    /**
     * @param Exchange $exchange
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param string $additionalFee
     * @param string $type
     */
    public function checkExchangeAmount(Exchange $exchange, string $amount, string $fee, string $realAmount, string $additionalFee, string $type): void
    {
        switch ($exchange->getTitle()) {
            case Exchange::KRAKEN:
                $fullAmount = $type === TransactionType::TYPE_SELL
                    ? CalculationHelper::sum($fee, $realAmount)
                    : CalculationHelper::sub($realAmount, $fee);
                break;
            default:
                $fullFee = $type === TransactionType::TYPE_BUY
                    ? CalculationHelper::sub($fee, $additionalFee)
                    : CalculationHelper::sum($fee, $additionalFee);
                $fullAmount = CalculationHelper::sum($fullFee, $realAmount);
        }

        if (CalculationHelper::comp($fullAmount, $amount) !== 0) {
            throw new IncorrectFeeException(ErrorCode::FEE_AND_REAL_AMOUNT_NOT_CORRECT);
        }
    }

    /**
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     */
    public function checkTransferAmount(string $amount, string $fee, string $realAmount): void
    {
        $fullAmount = CalculationHelper::sum($fee, $amount);

        if (CalculationHelper::comp($fullAmount, $realAmount) !== 0) {
            throw new IncorrectFeeException(ErrorCode::FEE_AND_REAL_AMOUNT_NOT_CORRECT);
        }
    }

    /**
     * @param string $code
     * @return TransactionType
     */
    public function getTransactionType(string $code): TransactionType
    {
        $transactionType = $this->transactionTypeRepository->findOneBy(['code' => $code]) ;
        if (!$transactionType instanceof TransactionType) {
            throw new NotFoundHttpException(ErrorCode::TRANSACTION_TYPE_NOT_FOUND);
        }

        return $transactionType;
    }

    /**
     * @param Transaction $transaction
     * @param \DateTime $date
     * @param string $amount
     * @param string $fee
     * @param string $realAmount
     * @param string $rateNominal
     * @return Transaction
     */
    public function updateExchangeTransaction(Transaction $transaction, \DateTime $date, string $amount, string $fee,
                                         string $realAmount, string $rateNominal): Transaction
    {
        $transaction->setDate($date)
            ->setAmount($amount)
            ->setRealAmount($realAmount)
            ->setFee($fee)
            ->setRateNominal($rateNominal);

        return $transaction;
    }

}