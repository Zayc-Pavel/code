<?php

namespace App\Service\Notification\Builder;

use App\DBAL\Type\NotificationInitiatorType;
use App\DBAL\Type\NotificationTargetType;
use App\DBAL\Type\NotificationTransportType;
use App\DBAL\Type\NotificationVisibleType;
use App\Entity\Notification\Application;
use App\Entity\Notification\Notification;
use App\Entity\Notification\NotificationTargetBe;
use App\Entity\Notification\NotificationTargetCompany;
use App\Entity\Notification\NotificationTargetCountry;
use App\Entity\Notification\NotificationTargetDepartment;
use App\Entity\Notification\NotificationTargetEmployee;
use App\Entity\Notification\NotificationTargetInterface;
use App\Entity\Notification\NotificationTransport;
use App\Entity\Notification\NotificationTransportContent;
use App\Entity\PersonalArea\Address\Country;
use App\Entity\PersonalArea\Department\Department;
use App\Entity\PersonalArea\User\Employee;
use App\Event\EventStore;
use App\Event\Notification\NotificationCreated;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class NotificationBuilder
 * @package App\Service\Notification\Builder
 */
class NotificationBuilder
{
    /** @var Notification */
    private $notification;

    /** @var NotificationTargetInterface[] */
    private $targets;

    /** @var NotificationTransport[] */
    private $transports;

    /** @var bool */
    private $ready;

    public function __construct()
    {
        $this->notification = new Notification();
        $this->notification
            ->setInitiatorType(NotificationInitiatorType::USER)
            ->setVisible(NotificationVisibleType::AUTO)
            ->setPlannedAt(new \DateTimeImmutable())
        ;
        $this->targets = [];
        $this->transports = [];
        $this->ready = false;
    }

    /**
     * @return static
     */
    public static function create(): self
    {
        return new self();
    }

    /**+
     * @param \DateTimeInterface $sendAt
     * @return $this
     */
    public function setSendAt(\DateTimeInterface $sendAt): self
    {
        $this->notification->setPlannedAt($sendAt);

        return $this;
    }

    /**
     * @param Employee $employee
     * @return $this
     */
    public function setUserOwner(Employee $employee): self
    {
        $this->notification->setUserOwner($employee);

        return $this;
    }

    /**
     * @param Application $application
     * @return $this
     */
    public function setSystemOwner(Application $application): self
    {
        $this->notification->setSystemOwner($application);

        return $this;
    }

    /**
     * @param string $visibility
     * @return $this
     */
    public function setVisibility(string $visibility): self
    {
        if (!in_array($visibility, NotificationVisibleType::getChoices())) {
            throw new \LogicException(sprintf('Undefined visibility type "%s"', $visibility));
        }

        $this->notification->setVisible($visibility);

        return $this;
    }

    /**
     * @param string $initiatorType
     * @return $this
     */
    public function setInitiatorType(string $initiatorType): self
    {
        if (!in_array($initiatorType, NotificationInitiatorType::getChoices())) {
            throw new \LogicException(sprintf('Undefined initiator type "%s"', $initiatorType));
        }

        $this->notification->setInitiatorType($initiatorType);

        return $this;
    }

    /**
     * @param string $transportType
     * @param NotificationMessage|array $notificationMessage
     * @return $this
     */
    public function withTransport(string $transportType, $notificationMessage): self
    {
        if (!in_array($transportType, NotificationTransportType::getChoices())) {
            throw new \LogicException(sprintf('Undefined transport type "%s"', $transportType));
        }

        if (!isset($this->transports[$transportType])) {
            $this->transports[$transportType] = new NotificationTransport($this->notification, $transportType);
            $transportContent = new NotificationTransportContent($this->transports[$transportType]);
            $this->transports[$transportType]->setContent($transportContent);
        }

        if (is_array($notificationMessage)) {
            $notificationMessage = NotificationMessage::createFromArray($notificationMessage);
        }

        if (!$notificationMessage instanceof NotificationMessage) {
            throw new \LogicException(sprintf('Undefined type of message'));
        }

        $transportContent = $this->transports[$transportType]->getContent();
        if ($title = $notificationMessage->getTitle()) {
            $transportContent->setTitle($title);
        }
        if ($description = $notificationMessage->getDescription()) {
            $transportContent->setDescription($description);
        }
        if ($data = $notificationMessage->getData()) {
            $transportContent->setData($data);
        }
        if ($translationData = $notificationMessage->getTranslations()) {
            $transportContent->setTranslationData($translationData);
        }

        return $this;
    }

    /**
     * @param string $transportType
     * @return NotificationTransport|null
     */
    public function getTransport(string $transportType): ?NotificationTransport
    {
        return $this->transports[$transportType] ?? null;
    }

    /**
     * @param string $targetType
     * @param array $targetObjects
     * @return $this
     */
    public function addTarget(string $targetType, array $targetObjects = []): self
    {
        if (!in_array($targetType, NotificationTargetType::getChoices())) {
            throw new \LogicException(sprintf('Undefined target type "%s"', $targetType));
        }

        if ($targetType === NotificationTargetType::COMPANY) {
            $this->targets[] = new NotificationTargetCompany($this->notification);
            $this->notification->setTargetTypes([$targetType]);
        } elseif (!empty($targetObjects)) {
            foreach ($targetObjects as $object) {
                $target = null;
                switch ($targetType) {
                    case NotificationTargetType::EMPLOYEE:
                        if ($object instanceof Employee) {
                            $target = new NotificationTargetEmployee($this->notification, $object);
                        }
                        break;
                    case NotificationTargetType::BE:
                        if ($object instanceof Department) {
                            $target = new NotificationTargetBe($this->notification, $object);
                        }
                        break;
                    case NotificationTargetType::DEPARTMENT:
                        if ($object instanceof Department) {
                            $target = new NotificationTargetDepartment($this->notification, $object);
                        }
                        break;
                    case NotificationTargetType::COUNTRY:
                        if ($object instanceof Country) {
                            $target = new NotificationTargetCountry($this->notification, $object);
                        }
                        break;
                }

                if ($target) {
                    $this->targets[] = $target;
                    $this->notification->setTargetTypes([$targetType]);
                }
            }
        }

        return $this;
    }

    /**
     * @return array|NotificationTargetInterface[]
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * @param ObjectManager|EntityManagerInterface $manager
     * @return Notification
     * @throws \Doctrine\ORM\ORMException
     */
    public function build(ObjectManager $manager): Notification
    {
        if ($this->ready) {
            return $this->notification;
        }

        if (empty($this->transports)) {
            throw new \LogicException('Empty transports');
        }

        if (empty($this->targets)) {
            throw new \LogicException('Empty targets');
        }

        if ($this->notification->getInitiatorType() === NotificationInitiatorType::USER) {
            if (!$this->notification->getUserOwner()) {
                throw new \LogicException('Employee required');
            }
        }

        $manager->persist($this->notification);
        foreach ($this->transports as $transport) {
            $manager->persist($transport);
        }

        foreach ($this->targets as $target) {
            $manager->persist($target);
        }

        $this->ready = true;

        return $this->notification;
    }

    /**
     * @param ObjectManager $manager
     * @param bool $flush
     * @return Notification
     */
    public function buildAndSend(ObjectManager $manager, bool $flush = true): Notification
    {
        $notification = $this->build($manager);
        EventStore::remember(new NotificationCreated($notification));
        if ($flush) {
            $manager->flush();
        }

        return $notification;
    }
}
