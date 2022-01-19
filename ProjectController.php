<?php

namespace App\Http\Controller\Project;

use App\DBAL\Type\FileStatus;
use App\Entity\Order\FeaturedProject;
use App\Entity\Order\Order;
use App\Entity\Order\Project;
use App\Entity\Order\ProjectFile;
use App\Handler\Order\ApproveHandler;
use App\Handler\Order\Project\ChangeRequestHandler;
use App\Handler\Order\Project\SelectHandler;
use App\Handler\Order\Project\UploadHandler;
use App\Handler\Review\CreateHandler;
use App\Http\Controller\ApiController;
use App\Http\ParamConverter\RequestCollection;
use App\Http\Transformer\File\FileWithStatusTransformer;
use App\Http\Transformer\Order\OrderItemTransformer;
use App\Http\Transformer\Order\OrderTransformer;
use App\Http\Transformer\Project\FeaturedProjectTransformer;
use App\Http\Transformer\Project\ProjectTransformer;
use App\Repository\FeaturedProjectRepository;
use App\Service\Configuration\ConfigurationManager;
use App\Service\Order\CalculateService;
use App\Service\Security\OrderItemVoter;
use App\Service\Security\ProjectVoter;
use Doctrine\Common\Collections\Collection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Config;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ProjectsController.
 *
 * @Route("/projects")
 * @package App\Http\Controller\Project
 */
class ProjectController extends ApiController {

    const PROJECT_CHANGE_REQUEST_PRICE = 'PROJECT_CHANGE_REQUEST_PRICE';

    /**
     * @Config\Route("/featured")
     * @Config\Method("GET")
     *
     * @param \App\Repository\FeaturedProjectRepository $repository
     * @param \App\Http\ParamConverter\RequestCollection $requestCollection
     * @param \App\Http\Transformer\Project\FeaturedProjectTransformer $transformer
     * @param \App\Service\Configuration\ConfigurationManager $configuration
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listFeatured(
        FeaturedProjectRepository $repository,
        RequestCollection $requestCollection,
        FeaturedProjectTransformer $transformer,
        ConfigurationManager $configuration
    ): JsonResponse {
        if (!$configuration->getParameter('HIDE_FEATURED_PROJECTS')) {
            return $this->collection($repository->findFeatured($requestCollection), $transformer);
        }

        return $this->emptyCollection();
    }

    /**
     * @Config\Route("/featured/{featuredProject}")
     * @Config\Method("GET")
     *
     * @param \App\Entity\Order\FeaturedProject $featuredProject
     * @param \App\Http\Transformer\Project\FeaturedProjectTransformer $transformer
     * @param \App\Service\Configuration\ConfigurationManager $configuration
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function detailsFeatured(
        FeaturedProject $featuredProject,
        FeaturedProjectTransformer $transformer,
        ConfigurationManager $configuration
    ): JsonResponse {
        if (!$configuration->getParameter('HIDE_FEATURED_PROJECTS')) {
            return $this->item($featuredProject, $transformer);
        }

        return $this->emptyData();
    }

    /**
     * @Config\Route("/{project}/select")
     * @Config\Method("PUT")
     * @Config\Security("has_role('ROLE_CONSUMER') and is_granted(constant('App\\Service\\Security\\ProjectVoter::IN_PROJECT_CONSUMER'), project)")
     *
     * @param \App\Entity\Order\Project $project
     * @param \App\Handler\Order\Project\SelectHandler $handler
     * @param \App\Http\Transformer\Order\OrderTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function selectProject(Project $project, SelectHandler $handler, OrderTransformer $transformer): JsonResponse {
        $this->denyAccessUnlessGranted(OrderItemVoter::IS_ACTIVE, $project->getOrderItem());
        $order = $handler($project);
        $this->flushChanges();

        return $this->item($order, $transformer);
    }

    /**
     * @Config\Route("/{project}")
     * @Config\Method("GET")
     * @Config\Security("is_granted(constant('App\\Service\\Security\\OrderVoter::IS_IN_PROJECT'), project)")
     * @param \App\Entity\Order\Order $project
     * @param \App\Http\Transformer\Order\OrderTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function details(Order $project, OrderTransformer $transformer): JsonResponse {
        return $this->item($project, $transformer);
    }

    /**
     * @Config\Route("/{project}/reviews", requirements={"project"="\d+"})
     * @Config\Method("POST")
     * @Config\Security("has_role('ROLE_CONSUMER')")
     *
     * @param \App\Entity\Order\Project $project
     * @param \App\Http\Controller\Project\CreateReviewRequest $request
     * @param \App\Handler\Review\CreateHandler $handler
     * @param \App\Http\Transformer\Order\OrderTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function createReview(
        Project $project,
        CreateReviewRequest $request,
        CreateHandler $handler,
        OrderTransformer $transformer
    ): JsonResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::APPROVE, $project);
        $this->denyAccessUnlessGranted(ProjectVoter::REVIEW, $project);
        $this->denyAccessUnlessGranted(OrderItemVoter::IS_ACTIVE, $project->getOrderItem());
        $handler($request, $this->getUser(), $project);
        $this->flushChanges();

        return $this->item($project->getOrderItem()->getOrder(), $transformer);
    }

    /**
     * @Config\Route("")
     * @Config\Method("POST")
     * @Config\Security("is_granted(constant('App\\Service\\Security\\OrderItemVoter::SUITABLE_ORDER_ITEM'), request)")
     * @param \App\Handler\Order\ApproveHandler $handler
     * @param \App\Http\Transformer\Order\OrderTransformer $orderTransformer
     * @param \App\Http\Controller\Project\ApproveOrderItemRequest $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function approveOrderItem(
        ApproveHandler $handler,
        OrderTransformer $orderTransformer,
        ApproveOrderItemRequest $request
    ): JsonResponse {
        $order = $handler($request, $this->getUser());
        $this->flushChanges();
        return $this->item($order, $orderTransformer);
    }

    /**
     * @Config\Route("/{project}/change-request")
     * @Config\Method("POST")
     * @Config\Security("has_role('ROLE_CONSUMER') and is_granted(constant('App\\Service\\Security\\ProjectVoter::IN_PROJECT_CONSUMER'), project)")
     *
     * @param \App\Http\Controller\Project\ChangeRequest $request
     * @param \App\Entity\Order\Project $project
     * @param \App\Handler\Order\Project\ChangeRequestHandler $handler
     * @param \App\Http\Transformer\Order\OrderItemTransformer $orderItemTransformer
     * @param \App\Http\Transformer\Order\OrderTransformer $orderTransformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function changeRequestProject(
        ChangeRequest $request,
        Project $project,
        ChangeRequestHandler $handler,
        OrderItemTransformer $orderItemTransformer,
        OrderTransformer $orderTransformer
    ): JsonResponse {
        $this->denyAccessUnlessGranted(OrderItemVoter::IS_ACTIVE, $project->getOrderItem());
        $project = $handler($request, $this->getUser(), $project);
        $this->flushChanges();

        $orderItemTransformer->setCurrentUser($this->getUser());
        return $this->item($project, $orderTransformer);
    }

    /**
     * @Config\Route("/{project}/change-request/price")
     * @Config\Method("GET")
     * @Config\Security("has_role('ROLE_CONSUMER') and is_granted(constant('App\\Service\\Security\\ProjectVoter::IN_PROJECT_CONSUMER'), project)")
     *
     * @param \App\Entity\Order\Project $project
     * @param \App\Service\Configuration\ConfigurationManager $configurationManager
     * @param \App\Service\Order\CalculateService $calculateService
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function calculateChangeRequestProject(
        Project $project,
        ConfigurationManager $configurationManager,
        CalculateService $calculateService
    ): JsonResponse {
        $price = $calculateService->calculateChangeRequest($project, $configurationManager->getParameter(self::PROJECT_CHANGE_REQUEST_PRICE));

        return $this->json(['data' => ['price' => $price]]);
    }

    /**
     * @Config\Route("/{project}/upload")
     * @Config\Method("POST")
     * @Config\Security("has_role('ROLE_PROVIDER') and is_granted(constant('App\\Service\\Security\\ProjectVoter::IS_HIS_PROJECT'), project)")
     *
     * @param \App\Entity\Order\Project $project
     * @param \App\Http\Controller\Project\UploadRequest $uploadRequest
     * @param \App\Handler\Order\Project\UploadHandler $uploadHandler
     * @param \App\Http\Transformer\Project\ProjectTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function uploadAudio(Project $project, UploadRequest $uploadRequest, UploadHandler $uploadHandler, ProjectTransformer $transformer) {
        $this->denyAccessUnlessGranted(OrderItemVoter::IS_ACTIVE, $project->getOrderItem());
        $project = $uploadHandler($uploadRequest, $project, $this->getUser());
        $this->flushChanges();

        return $this->item($project, $transformer);
    }

    /**
     * @Config\Route("/{project}/sample-file")
     * @Config\Method("GET")
     * @Config\Security("has_role('ROLE_PROVIDER') and is_granted(constant('App\\Service\\Security\\ProjectVoter::IS_HIS_PROJECT'), project)")
     *
     * @param \App\Entity\Order\Project $project
     * @param \App\Http\Transformer\File\FileWithStatusTransformer $fileWithStatusTransformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function sampleFileStatus(Project $project, FileWithStatusTransformer $fileWithStatusTransformer) {
        $response = new JsonResponse([], Response::HTTP_NOT_FOUND);
        $sampleFiles = $project->getSampleFiles();
        if ($sampleFiles instanceof Collection) {
            $file = $sampleFiles->first();
            if ($file instanceof ProjectFile && $file->getFile()->getStatus() === FileStatus::UPLOADED) {
                $response = $this->item($file->getFile(), $fileWithStatusTransformer);
            }
        }

        return $response;
    }

}
