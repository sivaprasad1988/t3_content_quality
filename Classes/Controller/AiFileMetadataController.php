<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Woit\T3ContentQuality\Service\FileMetadataGenerator;

class AiFileMetadataController
{
    public function __construct(
        private readonly FileMetadataGenerator $fileMetadataGenerator,
        private readonly ResourceFactory $resourceFactory,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $referer = (string)($request->getServerParams()['HTTP_REFERER'] ?? '');
        $fallback = '/typo3/module/file/FilelistList';

        $fileUid = (int)($request->getQueryParams()['fileUid'] ?? 0);
        if ($fileUid <= 0) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (ResourceDoesNotExistException) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        if (!$file->isImage() || !$file->isIndexed() || !$file->checkActionPermission('editMeta')) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        $metaUid = (int)($file->getMetaData()->get()['uid'] ?? 0);
        if ($metaUid <= 0) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        $this->fileMetadataGenerator->generate($file);

        // Redirect to metadata edit form so user can review/adjust generated values
        $editUrl = $this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit[sys_file_metadata][' . $metaUid . ']' => 'edit',
            'returnUrl' => $referer ?: $fallback,
        ]);

        return new RedirectResponse((string)$editUrl, 303);
    }
}
