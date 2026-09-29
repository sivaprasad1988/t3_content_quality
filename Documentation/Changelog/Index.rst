..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

..  _changelog-0-1-1:

0.1.1
=====

*   Extension title renamed to "AI Content Quality Assistant".
*   Site-specific content in the documentation screenshots obfuscated.
*   Packagist and TER links added to the installation documentation.
*   Composer keywords extended.

..  _changelog-0-1-0:

0.1.0
=====

Initial release (beta) for TYPO3 14.

*   Rule-based accessibility, SEO, readability and duplicate-content checks
    with scores.
*   Quality panel in the page module and Content Quality backend module with
    score trend, heading map, link check and all-pages overview.
*   AI improvement suggestions and internal link suggestions.
*   AI fixes for page title, meta description and image ALT texts, stored as
    pending changes for review; batch generation for up to 15 pages.
*   Schema.org advisor with rule-based and AI detection, editable JSON-LD,
    approval workflow, CSV export and frontend output via PSR-15 middleware.
*   CKEditor 5 AI text generator, with the ready-made RTE preset
    ``t3_content_quality`` and an importable :file:`Configuration/RTE/Plugin.yaml`.
*   AI image metadata in the file list, using EXIF/IPTC data first.
*   Providers: Anthropic (default model ``claude-sonnet-5-5`` with
    configurable effort and server-side refusal fallback), OpenAI, Ollama
    (configurable URL, image descriptions with multimodal models), all
    through one shared client.
*   Unit tests for scoring, schema handling, JSON-LD escaping and the AI
    client.
*   All backend actions check page, table and language permissions; applied
    fixes are written through the DataHandler (history, workspaces, cache
    clearing, translations).
*   Approved JSON-LD is re-encoded before output so it can not break out of
    the ``<script>`` element.
