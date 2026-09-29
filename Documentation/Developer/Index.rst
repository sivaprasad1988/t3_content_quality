..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

..  contents::
    :local:
    :depth: 2

..  _developer-architecture:

Architecture
============

*   All classes are registered through :file:`Configuration/Services.yaml`
    with autowiring and use constructor injection.
*   The page module panel and the file list button are PSR-14 event
    listeners.
*   The JSON-LD output is a PSR-15 frontend middleware.
*   Backend actions are backend routes. The Content Quality module is an
    Extbase backend module.
*   The rich-text editor button is a CKEditor 5 plugin loaded as an ES
    module.

The analysis pipeline, started by
:php:`AnalysisOrchestrator::analyze()`:

..  code-block:: text

    PageContentExtractor::extractFromPage()       page data from the database
    RenderedHeadingExtractor::extract()           headings from the rendered page (optional)
    AccessibilityAnalyzer / SeoAnalyzer /
    ReadabilityAnalyzer / DuplicateContentAnalyzer  rule-based issues
    SchemaAdvisor                                 PageIntentDetector → SchemaMapper
                                                  → JsonLdGenerator → SchemaValidator
    AiAnalyzer, InternalLinkAnalyzer              only if AI is enabled
    ScoreCalculator                               scores
    → tx_t3contentquality_result + tx_t3contentquality_history

..  _developer-api:

Using the analysis in your own code
===================================

Inject :php:`Woit\T3ContentQuality\Service\AnalysisOrchestrator`:

..  code-block:: php
    :caption: EXT:my_extension/Classes/Command/AnalyzeCommand.php

    use Woit\T3ContentQuality\Service\AnalysisOrchestrator;

    final class AnalyzeCommand extends Command
    {
        public function __construct(
            private readonly AnalysisOrchestrator $analysisOrchestrator,
        ) {
            parent::__construct();
        }

        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $result = $this->analysisOrchestrator->analyze(pageUid: 42, languageUid: 0);
            $output->writeln('Overall score: ' . $result->overallScore);
            return Command::SUCCESS;
        }
    }

Public methods of :php:`AnalysisOrchestrator`:

:php:`analyze(int $pageUid, int $languageUid = 0): AnalysisResult`
    Runs all checks and stores the result.

:php:`getStoredResult(int $pageUid, int $languageUid = 0): ?array`
    Returns the stored result without analysing again.

:php:`analyzeSchemaWithAi(int $pageUid, int $languageUid = 0): string`
    Runs AI schema detection and updates the stored result. Returns a
    string starting with ``ok:`` or ``error:``.

:php:`checkLinks(int $pageUid, int $languageUid, LinkChecker $linkChecker): array`
    Checks the links of the page and stores the result.

:php:`getHistory(int $pageUid, int $languageUid = 0, int $limit = 20): array`
    Score history of a page.

:php:`getAllResults(): array`
    Stored results of all pages in the default language.

:php:`getUnanalyzedPageCount(): int`
    Number of visible standard pages without a result.

..  note::

    The extension is in beta state. Class names and method signatures may
    change until version 1.0. There is no PHP interface for analyzers yet.

..  _developer-models:

Domain models
=============

AnalysisIssue
-------------

Immutable value object:

..  code-block:: php

    use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;

    $issue = new AnalysisIssue(
        category: AnalysisIssue::CATEGORY_ACCESSIBILITY,
        severity: AnalysisIssue::SEVERITY_ERROR,
        message: 'Image has no ALT text.',
        suggestion: 'Add a descriptive ALT text.',
    );

Categories: ``CATEGORY_ACCESSIBILITY``, ``CATEGORY_SEO``,
``CATEGORY_READABILITY``, ``CATEGORY_SCHEMA``.
Severities: ``SEVERITY_ERROR``, ``SEVERITY_WARNING``, ``SEVERITY_INFO``.

AnalysisResult
--------------

Collects issues and scores during an analysis:

..  code-block:: php

    $result->overallScore;          // int, average of accessibility, SEO, readability
    $result->accessibilityScore;    // int 0–100
    $result->seoScore;              // int 0–100
    $result->readabilityScore;      // int 0–100
    $result->schemaScore;           // int 0–100
    $result->providerUsed;          // string
    $result->modelUsed;             // string
    $result->metadata;              // array, page facts
    $result->getIssues();           // AnalysisIssue[]
    $result->getIssuesByCategory('seo');
    $result->getAiSuggestions();    // string[]

Page data
---------

:php:`PageContentExtractor::extractFromPage()` returns an array with the keys
``page_uid``, ``page_title``, ``seo_title``, ``meta_description``,
``abstract``, ``author``, ``slug``, ``doktype``, ``crdate``, ``tstamp``,
``schema_type_override``, ``headings`` (list of ``level`` and ``text``),
``images``, ``links`` (list of ``text`` and ``href``), ``text_content`` and
``ctypes``. :php:`AnalysisOrchestrator` adds ``page_url``.

..  _developer-services:

Other services
==============

:php:`Woit\T3ContentQuality\Security\PermissionService`
    :php:`canShowPage()`, :php:`canEditPage()`,
    :php:`canEditPageContent()`, :php:`canApplyFixType()` and
    :php:`filterShowablePageUids()` for the current backend user.

:php:`Woit\T3ContentQuality\Service\PageFixApplier`
    Stores AI proposals as pending rows and applies them with
    :php:`applyRows()` through the DataHandler.

:php:`Woit\T3ContentQuality\Domain\Repository\ApprovedSchemaRepository`
    Reads approved JSON-LD. Used by the frontend middleware.

:php:`Woit\T3ContentQuality\Service\OllamaEndpoint`
    Resolves the Ollama URL from :confval:`t3cq-ollamaUrl`.

..  _developer-events:

Events used
===========

:php:`TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent`
    :php:`PageModuleContentListener` adds the quality panel to the page
    module.

:php:`TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent`
    :php:`FilelistActionsEventListener` adds the metadata button to image
    rows in the file list.

Both listeners are registered in :file:`Configuration/Services.yaml` with
the identifiers ``t3contentquality.pageModulePanel`` and
``t3contentquality.filelistAiMetadata``. To remove a listener, override its
service definition in your own :file:`Services.yaml`.

..  _developer-backend-routes:

Backend routes
==============

All routes require a backend login and the TYPO3 route token. Build URLs
with :php:`TYPO3\CMS\Backend\Routing\UriBuilder`. Each route checks the
page permissions listed in :ref:`admin-permissions-fixes`; requests for
pages the user may not access are ignored.

..  list-table::
    :header-rows: 1
    :widths: 35 10 55

    *   -   Route identifier
        -   Method
        -   Purpose
    *   -   ``t3contentquality_analyze``
        -   GET
        -   Analyse ``pageUid``/``languageUid``, redirect to the page module.
    *   -   ``t3contentquality_fix``
        -   GET
        -   Generate pending fixes; ``fixType`` is ``title``,
            ``description``, ``alt_text`` or ``all``.
    *   -   ``t3contentquality_pending_apply``
        -   GET, POST
        -   Apply pending fixes of a page, or the fixes listed in ``uids``.
    *   -   ``t3contentquality_pending_discard``
        -   GET, POST
        -   Discard pending fixes of a page, or the fixes listed in
            ``uids``.
    *   -   ``t3contentquality_bulk_fix_generate``
        -   POST
        -   Generate pending fixes for ``pageUids`` (max. 15) of type
            ``fixType``.
    *   -   ``t3contentquality_check_links``
        -   GET
        -   Check the links of a page.
    *   -   ``t3contentquality_schema_ai_detect``
        -   GET
        -   AI schema detection.
    *   -   ``t3contentquality_schema_approve``
        -   POST
        -   Store ``jsonld`` as approved schema.
    *   -   ``t3contentquality_schema_reject``
        -   GET
        -   Delete the approved schema.
    *   -   ``t3contentquality_schema_export``
        -   GET
        -   CSV export.
    *   -   ``t3contentquality_ai_file_metadata``
        -   GET
        -   Fill metadata of the image ``fileUid``, redirect to the
            metadata form.

AJAX route ``t3contentquality_generate_text`` (POST, JSON body with
``prompt``, ``tone``, ``language``, ``format``, ``maxChars``,
``languageUid``) returns ``{"text": "…"}`` or ``{"error": "…"}``.

..  _developer-middleware:

Frontend middleware
===================

:php:`Woit\T3ContentQuality\Middleware\JsonLdInjector` is registered as
``woit/t3-content-quality/json-ld-injector``. It re-encodes the stored
JSON-LD with ``JSON_HEX_TAG``, so ``<`` and ``>`` in values can not end the
``<script>`` element. To disable it, for example
if you render JSON-LD yourself:

..  code-block:: php
    :caption: EXT:my_sitepackage/Configuration/RequestMiddlewares.php

    return [
        'frontend' => [
            'woit/t3-content-quality/json-ld-injector' => [
                'disabled' => true,
            ],
        ],
    ];

..  _developer-schema:

Schema types
============

Supported schema.org types and their required and recommended properties
are defined in :php:`Woit\T3ContentQuality\Schema\SchemaTypes`. Rule-based
detection is in :php:`PageIntentDetector`, the mapping of page data to
properties in :php:`SchemaMapper`. Both are regular services and can be
replaced in :file:`Services.yaml`.

..  _developer-ai:

AI providers
============

All AI requests go through
:php:`Woit\T3ContentQuality\Service\AiProviderClient`:

:php:`call(string $prompt, AiSettings $settings): string`
    Text prompt, returns the model's answer.

:php:`callVision(string $prompt, string $imageBase64, string $mimeType, AiSettings $settings): string`
    Prompt plus one image.

:php:`Woit\T3ContentQuality\Service\AiSettingsFactory::create()` reads the
extension configuration and returns an immutable
:php:`Woit\T3ContentQuality\Service\AiSettings` object (provider, key,
model, limits). The default model IDs are defined only there.

Requests are sent with TYPO3's :php:`TYPO3\CMS\Core\Http\RequestFactory`
instead of the vendor SDKs, so the extension also works in classic mode
installations without Composer. For Anthropic the client

*   sends ``output_config.effort`` and ``fallbacks: "default"`` only to
    models that accept them (see :confval:`t3cq-anthropicEffort` and
    :confval:`t3cq-anthropicFallback`),
*   reads the answer by content block type, because thinking blocks can
    come first,
*   turns ``stop_reason: refusal`` into an exception with the refusal
    category.

To add a provider, add a ``match`` arm in :php:`AiProviderClient` and the
provider's settings in :php:`AiSettingsFactory` and
:file:`ext_conf_template.txt`.

..  _developer-javascript:

JavaScript modules
==================

:file:`Configuration/JavaScriptModules.php` maps the import prefix
``@woit/t3-content-quality/`` to
:file:`EXT:t3_content_quality/Resources/Public/JavaScript/`.

:file:`CKEditor/ai-text-generator.js`
    CKEditor 5 plugin ``AiTextGenerator`` with the toolbar item
    ``aiTextGenerator``.

:file:`backend-loader.js`, :file:`panel-loader.js`
    Loading indicators for the module and the panel.

..  _developer-testing:

Tests
=====

Unit tests are in :file:`Tests/Unit/` and use PHPUnit 11. They cover the
score calculation, schema validation and type detection, the JSON-LD
escaping in the middleware, the AI settings, the request and response
handling of :php:`AiProviderClient` and the parsing of AI answers. No
network access or database is needed.

In a standalone checkout of the extension:

..  code-block:: bash

    composer install
    composer test:unit

In a TYPO3 project that has PHPUnit installed:

..  code-block:: bash

    vendor/bin/phpunit -c vendor/woit/t3-content-quality/Build/phpunit/UnitTests.xml

Permission checks and the DataHandler integration need a database and are
not covered by unit tests.
