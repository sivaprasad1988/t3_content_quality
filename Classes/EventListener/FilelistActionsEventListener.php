<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ActionGroup;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;

class FilelistActionsEventListener
{
    // Fields that can be auto-generated (EXIF/IPTC or AI vision)
    private const AUTO_FIELDS = ['alternative', 'title', 'description', 'caption'];

    public function __construct(
        private readonly ComponentFactory $componentFactory,
        private readonly IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function __invoke(ProcessFileListActionsEvent $event): void
    {
        if (!$event->isFile()) {
            return;
        }

        $resource = $event->getResource();
        if (!$resource instanceof File || !$resource->isImage()) {
            return;
        }

        if (!$resource->isIndexed() || !$resource->checkActionPermission('editMeta')) {
            return;
        }

        $meta = $resource->getMetaData()->get();
        $missingFields = array_values(array_filter(
            self::AUTO_FIELDS,
            static fn($field) => empty(trim((string)($meta[$field] ?? '')))
        ));

        // All auto-fillable fields present — no need for button
        if (empty($missingFields)) {
            return;
        }

        $url = $this->uriBuilder->buildUriFromRoute(
            't3contentquality_ai_file_metadata',
            ['fileUid' => $resource->getUid()]
        );

        $title = 'KI-Metadaten generieren (' . implode(', ', $missingFields) . ')';

        $button = $this->componentFactory->createGenericButton();
        $button->setTag('a');
        $button->setLabel($title);
        $button->setTitle($title);
        $button->setIcon($this->iconFactory->getIcon('actions-wand-sparkles', IconSize::SMALL));
        $button->setHref((string)$url);

        // Place before the metadata edit button if it exists in primary, otherwise append
        if ($event->hasAction('metadata', ActionGroup::primary)) {
            $event->setAction($button, 'aiMetadata', ActionGroup::primary, 'metadata');
        } else {
            $event->setAction($button, 'aiMetadata', ActionGroup::primary);
        }
    }
}
