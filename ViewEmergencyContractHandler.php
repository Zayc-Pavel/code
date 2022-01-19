<?php

namespace App\Http\Handler\EmergencyContact;

use App\Entity\Address;
use App\Entity\MetricaViewProfile;
use App\Entity\User;
use App\Http\EventListener\ErrorCodes;
use App\Http\Exception\NotFound;
use App\Http\Exception\RegistrationNeededException;
use App\Http\Exception\UserDeletedException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ViewEmergencyContractHandler
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * ViewEmergencyContractHandler constructor.
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $em
     */
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $em
    ) {
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    /**
     * @param User|null $user
     * @param string $code
     * @return array
     * @throws RegistrationNeededException
     * @throws UserDeletedException
     */
    public function __invoke(?User $user, string $code)
    {
        if ($user instanceof User && $user->getCode() === $code && !$user->isDeleted()) {
            $result = $this->getCurrentUserInfo($user);
        } else {
            /** @var User $user */
            $user = $this->userRepository->findOneByCode($code);
            if (!$user instanceof User) {
                throw new NotFound(ErrorCodes::USER_NOT_FOUND);
            }

            if ($user->isDeleted()) {
                throw new UserDeletedException();
            }

            if ($user->getEmail()) {
                $this->saveVisit($user);
                $result = $this->getUnAuthUserInfo($user);
            } else {
                throw new RegistrationNeededException();
            }
        }

        return $result;
    }

    /**
     * @param User $user
     * @return array
     */
    private function getCurrentUserInfo(User $user): array
    {
        $profile = $user->getProfile();

        $result = $profile->toArray();
        if (array_key_exists(0, $profile->getAddresses()->toArray())) {
            /** @var Address $address */
            $address = $profile->getAddresses()->toArray()[0];
            $result = array_merge($result, $address->toArray());
        }

        $result['optin'] = $user->isOptin();
        $result['currentUser'] = true;

        return $result;
    }

    /**
     * @param User $user
     * @return array
     */
    private function getUnAuthUserInfo(User $user): array
    {
        $profile = $user->getProfile();
        $result = [];
        if ($profile->isShowName()) {
            $result = array_merge([
                'title' => $profile->getTitle(),
                'name' => $profile->getName(),
                'surname' => $profile->getSurname()
            ], $result);
        }

        if ($profile->isShowPhone()) {
            $result = array_merge(['phone' => $profile->getPhone()], $result);
        }

        $result = array_merge([
            'emergencyPhoneOne' => $profile->getEmergencyPhoneOne(),
            'emergencyPhoneTwo' => $profile->getEmergencyPhoneTwo()
        ], $result);

        $result['currentUser'] = false;
        $result['showName'] = $profile->isShowName();
        $result['showPhone'] = $profile->isShowPhone();

        return $result;
    }

    /**
     * @param User $user
     */
    private function saveVisit(User $user)
    {
        $clientIP = $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $metrica = (new MetricaViewProfile())
            ->setUser($user)
            ->setIp($clientIP);
        $this->em->persist($metrica);
        $this->em->flush();
    }
}
