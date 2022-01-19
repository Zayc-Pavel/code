<?php

namespace App\Messenger\EventSubscriber;

use App\Entity\Type\MarketType;
use App\Exception\AuthenticationException;
use App\Messenger\Command\Billbee\BillbeeImportInformationCommand;
use App\Messenger\Command\Market\AllegroExportInformationCommand;
use App\Messenger\Command\Market\AllegroUpdateStockCommand;
use App\Messenger\Command\Market\OttoExportInformationCommand;
use App\Messenger\Event\AllegroUpdateStockEvent;
use App\Messenger\Event\BillbeeImportInformationEvent;
use App\Messenger\Event\MarketAccountDataProcessingEvent;
use App\Repository\Security\AccountRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Class MarketEventSubscriber
 * @package App\Messenger\EventSubscriber
 */
class MarketEventSubscriber implements EventSubscriberInterface
{
    private $bus;
    private $accountRepository;

    public function __construct(
        MessageBusInterface $bus,
        AccountRepository $accountRepository
    ) {
        $this->bus = $bus;
        $this->accountRepository = $accountRepository;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            MarketAccountDataProcessingEvent::class => 'onMarketAccountDataProcessingEvent',
            BillbeeImportInformationEvent::class => 'onBillbeeImportInformationEvent',
            AllegroUpdateStockEvent::class => 'onAllegroUpdateStockEvent'
        ];
    }

    public function onAllegroUpdateStockEvent(AllegroUpdateStockEvent $event)
    {
        $command = new AllegroUpdateStockCommand($event->getUuid());
        $this->bus->dispatch($command);
    }

    public function onMarketAccountDataProcessingEvent(MarketAccountDataProcessingEvent $event)
    {
        $command = null;

        $account = $this->accountRepository->find($event->getAccountId());
        if (!$account) {
            throw new AuthenticationException(AuthenticationException::MISSING_ACCOUNT);
        }

        switch ($account->getMarket()) {
            case MarketType::ALLEGRO:
                $command = new AllegroExportInformationCommand($event->getAccountId(), $event->getLastExecute());
                break;
            case MarketType::OTTO:
                $command = new OttoExportInformationCommand($event->getAccountId(), $event->getLastExecute());
                break;
        }
        if ($command) {
            $this->bus->dispatch($command);
        }
    }

    public function onBillbeeImportInformationEvent(BillbeeImportInformationEvent $event)
    {
        $command = new BillbeeImportInformationCommand($event->getUuid());
        $this->bus->dispatch($command);
    }
}
