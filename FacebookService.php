<?php

namespace App\Services;

use App\DBAL\Type\FileType;
use App\Entity\FileReference;
use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserAccess;
use App\Helper\CUtils;
use App\Repository\ProfileRepository;
use App\Repository\SpecializationRepository;
use App\Repository\UserRepository;
use App\Services\Mail\MailService;
use App\Services\Storage\StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\Authentication\AccessToken;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Facebook\Helpers\FacebookRedirectLoginHelper;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class FacebookService
 * @package App\Services
 */
class FacebookService
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
     * @var SpecializationRepository
     */
    private $specializationRepository;

    /**
     * @var ProfileRepository
     */
    private $profileRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var string
     */
    private $localUploadDir;

    /**
     * @var ImageService
     */
    private $imageService;

    /**
     * @var StorageService
     */
    private $storageService;

    public function __construct(
        string $localUploadDir,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        SpecializationRepository $specializationRepository,
        ProfileRepository $profileRepository,
        UserService $userService,
        MailService $mailService,
        ImageService $imageService,
        StorageService $storageService
    ) {
        $this->localUploadDir = $localUploadDir;
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->specializationRepository = $specializationRepository;
        $this->profileRepository = $profileRepository;
        $this->userService = $userService;
        $this->mailService = $mailService;
        $this->imageService = $imageService;
        $this->storageService = $storageService;
    }

    /**
     * @return string
     * @throws FacebookSDKException
     */
    public function generateLoginUrl()
    {
        $fb = new Facebook([
            'app_id' => getenv('FB_APP_ID'),
            'app_secret' => getenv('FB_APP_SECRET'),
            'default_graph_version' => getenv('FB_GRAPH_VERSION'),
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email'];

        return $helper->getLoginUrl(getenv('FB_REDIRECT_URI'), $permissions);
    }

    /**
     * @return array
     * @throws FacebookSDKException
     */
    public function getUserInfo()
    {
        $fb = new Facebook([
            'app_id' => getenv('FB_APP_ID'),
            'app_secret' => getenv('FB_APP_SECRET'),
            'default_graph_version' => getenv('FB_GRAPH_VERSION'),
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $accessToken = $helper->getAccessToken();

        if (!$accessToken) {
            return null;
        }
        return [
            $fb->get('/me?fields=id,first_name,last_name,middle_name,email,picture.width(125).height(125)', $accessToken->getValue()),
            $accessToken
        ];
    }

    /**
     * @param AccessToken $accessToken
     * @param array $decodedData
     * @return mixed
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function handleResponse(AccessToken $accessToken, array $decodedData)
    {
        $this->saveUserAccess($accessToken, $decodedData);

        return $this->getLocalUser($decodedData);
    }

    /**
     * @param array $decodedData
     * @return mixed
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function getLocalUser(array $decodedData)
    {
        $user = $this->userRepository->findOneByEmail($decodedData['email']);
        if ($user instanceof User) {
            return [$user, true];
        }

        $specialization = $this->specializationRepository->findOneById(4);

        $user = (new User())
            ->setEmail($decodedData['email'])
            ->setRole(User::ROLE_PARTNER)
            ->setAlias(CUtils::translit($decodedData['email']))
            ->setPassword('')
            ->setActive(0);

        $middleFile = $this->storageService->generateFileReferenceFromSocial(
            $decodedData['picture']['data']['url'],
            $decodedData['email']
        );

        $profile = (new Profile())
            ->setFirstName(isset($decodedData['first_name']) ? $decodedData['first_name'] : '')
            ->setLastName(isset($decodedData['last_name']) ? $decodedData['last_name'] : '')
            ->setMiddleName(isset($decodedData['middle_name']) ? $decodedData['middle_name'] : '')
            ->setSpecialization($specialization)
            ->setOrderNumberInSpecialization(
                $this->profileRepository->getMaxOrderNumberInSpecialization($specialization) + 1
            )
            ->setUser($user)
            ->setMiddlePhoto($middleFile);

        $user->setProfile($profile);

        $this->em->persist($middleFile);
        $this->em->persist($profile);
        $this->em->persist($user);
        $this->em->flush();

        return [$user, false];
    }

    /**
     * @param AccessToken $accessToken
     * @param array $decodedData
     * @return UserAccess
     */
    private function saveUserAccess(AccessToken $accessToken, array $decodedData)
    {
        $userAccess = (new UserAccess())
            ->setType(UserAccess::TYPE_FB)
            ->setAccessToken($accessToken->getValue())
            ->setRemoteUserId($decodedData['id'])
            ->setExpiresIn(($accessToken->getExpiresAt())->format('Y:m:d h:i:s'))
            ->setEmail($decodedData['email']);

        $this->em->persist($userAccess);
        $this->em->flush();

        return $userAccess;
    }
}
