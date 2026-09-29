..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  contents::
    :local:
    :depth: 1

..  _configuration-extension:

Extension configuration
=======================

Open :guilabel:`System > Settings > Extension Configuration` and select
``t3_content_quality``. The settings are stored in
:file:`config/system/settings.php` under
``$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['t3_content_quality']``.

..  confval-menu::
    :name: extension-configuration
    :display: table
    :type:
    :default:

..  _configuration-provider:

General
-------

..  confval:: aiProvider
    :name: t3cq-aiProvider
    :type: options
    :default: ``ollama``

    The AI provider used by all AI features. Possible values:

    ``anthropic``
        Anthropic Claude. Requires :confval:`t3cq-anthropicApiKey`.

    ``openai``
        OpenAI. Requires :confval:`t3cq-openaiApiKey`.

    ``ollama``
        A local Ollama server, no API key needed.
        See :ref:`admin-ollama`.

..  confval:: enableAiAnalysis
    :name: t3cq-enableAiAnalysis
    :type: boolean
    :default: ``1``

    Uses the AI during page analysis for improvement suggestions and
    internal link suggestions, and enables AI schema detection.

    When disabled, the page analysis is rule-based only. The on-demand AI
    features (:guilabel:`Fix` buttons, text generator, image metadata) are
    not affected by this switch; they only need a configured provider.

..  confval:: maxTokens
    :name: t3cq-maxTokens
    :type: positive integer
    :default: ``16000``

    Upper limit of tokens per AI response for Anthropic and OpenAI. For
    current Claude models the limit also covers the model's thinking, so do
    not set it too low; you only pay for tokens actually generated. If the
    limit is reached before an answer is written, the log shows
    "Anthropic response hit maxTokens before producing text".
    Ollama ignores this setting.

..  _configuration-anthropic:

Anthropic
---------

..  confval:: anthropicApiKey
    :name: t3cq-anthropicApiKey
    :type: string
    :default: (empty)

    API key from the `Anthropic Console <https://console.anthropic.com/>`__.
    Required when :confval:`t3cq-aiProvider` is ``anthropic``.

..  confval:: anthropicModel
    :name: t3cq-anthropicModel
    :type: string
    :default: ``claude-sonnet-5-5``

    Model ID sent to the Anthropic Messages API, for example
    ``claude-sonnet-5-5`` (default), ``claude-opus-5-5`` (higher quality,
    higher cost) or ``claude-haiku-4-5`` (lower cost). All three accept
    images, which the :ref:`file list metadata button <filelist-ai-metadata>`
    needs. See the
    `list of Anthropic models <https://docs.anthropic.com/en/docs/about-claude/models>`__
    for current model IDs, as older models are retired over time.

..  confval:: anthropicEffort
    :name: t3cq-anthropicEffort
    :type: options
    :default: ``low``

    How much the model thinks before answering: ``low``, ``medium``,
    ``high`` or ``off``. The extension's tasks are short, so ``low`` keeps
    cost and response time down. The value is sent as
    ``output_config.effort``. It is not sent for ``claude-haiku-*`` and
    ``claude-3*`` models, which do not support it; ``off`` never sends it.

..  confval:: anthropicFallback
    :name: t3cq-anthropicFallback
    :type: boolean
    :default: ``1``

    If the model declines a request (``stop_reason: refusal``), Anthropic
    can retry it on another model server-side (``fallbacks: "default"``,
    beta header ``server-side-fallback-2026-07-01``). Only sent for models
    that support it (``claude-sonnet-5-5``, ``claude-opus-5-5``,
    ``claude-opus-5``, ``claude-fable-5-1``). Declined requests that are not
    retried are logged with their refusal category.

..  _configuration-openai:

OpenAI
------

..  confval:: openaiApiKey
    :name: t3cq-openaiApiKey
    :type: string
    :default: (empty)

    API key from the OpenAI platform. Required when
    :confval:`t3cq-aiProvider` is ``openai``.

..  confval:: openaiModel
    :name: t3cq-openaiModel
    :type: string
    :default: ``gpt-4o-mini``

    Model ID sent to the OpenAI Chat Completions API. For image metadata the
    model must support image input (``gpt-4o-mini`` does).

..  _configuration-ollama:

Ollama
------

..  confval:: ollamaModel
    :name: t3cq-ollamaModel
    :type: string
    :default: ``llama3.2``

    Name of an Ollama model that has been pulled on the Ollama server, for
    example ``llama3.2``, ``phi3`` or ``mistral``.

    For the :ref:`file list metadata button <filelist-ai-metadata>` the
    model must support images, for example ``llava`` or
    ``llama3.2-vision``. Text-only models ignore the image.

..  confval:: ollamaUrl
    :name: t3cq-ollamaUrl
    :type: string
    :default: ``http://ollama:11434``

    Base URL of the Ollama server, without ``/api``. The default is the
    host name of an ``ollama`` container in a Docker Compose or DDEV setup.
    For a local installation use ``http://localhost:11434``.

..  _configuration-api-keys-env:

Keep API keys out of version control
------------------------------------

:file:`config/system/settings.php` is usually committed to Git. Do not store
API keys there. Set them from an environment variable in
:file:`config/system/additional.php` instead:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['t3_content_quality']['aiProvider'] = 'openai';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['t3_content_quality']['openaiApiKey']
        = (string)getenv('OPENAI_API_KEY');

Values set in :file:`additional.php` override the values from the backend
settings form.

..  _configuration-ckeditor:

AI button in the rich-text editor
=================================

The :guilabel:`✦ AI` button needs the system extension ``rte_ckeditor``.
The extension does not change existing editor presets. Use one of these
three ways to add the button:

..  _configuration-ckeditor-preset:

Option 1: Use the preset of the extension
-----------------------------------------

The extension registers the RTE preset ``t3_content_quality`` in
:file:`ext_localconf.php`. It is the TYPO3 core default preset plus the AI
button at the end of the toolbar. Activate it in page TSconfig:

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/page.tsconfig

    RTE.default.preset = t3_content_quality

..  _configuration-ckeditor-import:

Option 2: Import the plugin into your own preset
------------------------------------------------

Import :file:`EXT:t3_content_quality/Configuration/RTE/Plugin.yaml` in your
own preset:

..  code-block:: yaml
    :caption: EXT:my_sitepackage/Configuration/RTE/Default.yaml

    imports:
      - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Default.yaml' }
      - { resource: 'EXT:t3_content_quality/Configuration/RTE/Plugin.yaml' }

The TYPO3 YAML loader appends list entries in the order the files are
loaded: imported files first, then the preset's own content. If your preset
defines its own ``toolbar.items``, the AI button therefore appears at the
**start** of the toolbar. Use option 3 to place it elsewhere.

..  _configuration-ckeditor-manual:

Option 3: Add the button manually
---------------------------------

Add the module and the toolbar item to your preset where you want them:

..  code-block:: yaml
    :caption: EXT:my_sitepackage/Configuration/RTE/Default.yaml

    editor:
      config:
        importModules:
          - { module: '@woit/t3-content-quality/CKEditor/ai-text-generator.js', exports: ['AiTextGenerator'] }
        toolbar:
          items:
            # … your existing toolbar items …
            - '|'
            - aiTextGenerator

See :ref:`ext_rte_ckeditor:configuration` for details on RTE presets.

..  _configuration-page-field:

Page field: schema type
=======================

The extension adds the field :guilabel:`Schema Type (Structured Data)`
(``tx_t3contentquality_schema_type``) to the :guilabel:`Meta Tags` palette
on the :guilabel:`Metadata` tab of the page properties. It overrides the
automatic schema.org type detection.

Options: *(Auto-detect)*, ``Event``, ``NewsArticle``, ``TouristAttraction``,
``LocalBusiness``, ``Organization``, ``FAQPage``, ``JobPosting``,
``Product``.

..  _configuration-middleware:

Frontend JSON-LD middleware
===========================

The PSR-15 middleware ``woit/t3-content-quality/json-ld-injector`` is
registered automatically for the frontend. It runs after
``typo3/cms-frontend/output-compression`` and before
``typo3/cms-frontend/send-response``.

For every HTML response it looks up an approved schema for the current page
and language in ``tx_t3contentquality_schema``. If one exists, it inserts

..  code-block:: html

    <script type="application/ld+json">…</script>

directly before ``</head>``. No TypoScript is needed. Pages without an
approved schema are not changed.
