<?php

namespace App\Http\Controller\File;

use App\DBAL\Type\FileReadableType;
use App\Entity\File\FileReference;
use App\Handler\File\CreateFileHandler;
use App\Http\Controller\ApiController;
use App\Http\Transformer\ModelTransformer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Config;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FileController.
 * @Route("/files")
 *
 * @package App\Http\Controller\File
 */
class FileController extends ApiController {

    /**
     * @Config\Route("/audio")
     * @Config\Method("POST")
     *
     * @param \App\Http\Controller\File\CreateAudioRequest $request
     * @param \App\Handler\File\CreateFileHandler $handler
     * @param \App\Http\Transformer\ModelTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createAudio(CreateAudioRequest $request, CreateFileHandler $handler, ModelTransformer $transformer): JsonResponse {
        return $this->item($handler->createFile($request, FileReadableType::AUDIO_TYPE), $transformer);
    }

    /**
     * @Config\Route("/image")
     * @Config\Method("POST")
     *
     * @param \App\Http\Controller\File\CreateImageRequest $request
     * @param \App\Handler\File\CreateFileHandler $handler
     * @param \App\Http\Transformer\ModelTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createImage(CreateImageRequest $request, CreateFileHandler $handler, ModelTransformer $transformer): JsonResponse {
        return $this->item($handler->createFile($request, FileReadableType::IMAGE_TYPE), $transformer);
    }

    /**
     * @Config\Route("/archive")
     * @Config\Method("POST")
     *
     * @param \App\Http\Controller\File\CreateArchiveRequest $request
     * @param \App\Handler\File\CreateFileHandler $handler
     * @param \App\Http\Transformer\ModelTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createArchive(CreateArchiveRequest $request, CreateFileHandler $handler, ModelTransformer $transformer): JsonResponse {
        return $this->item($handler->createFile($request, FileReadableType::ARCHIVE_TYPE), $transformer);
    }

    /**
     * @Config\Route("/document")
     * @Config\Method("POST")
     *
     * @param \App\Http\Controller\File\CreateDocumentRequest $request
     * @param \App\Handler\File\CreateFileHandler $handler
     * @param \App\Http\Transformer\ModelTransformer $transformer
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createDocument(CreateDocumentRequest $request, CreateFileHandler $handler, ModelTransformer $transformer): JsonResponse {
        return $this->item($handler->createFile($request, FileReadableType::DOCUMENT_TYPE), $transformer);
    }
}
