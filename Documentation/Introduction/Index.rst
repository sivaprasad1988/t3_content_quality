..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

The AI Content Quality Assistant adds an editorial quality layer to the TYPO3
backend. Rule-based checks run without any external service. When an AI
provider is configured, the extension also uses it to suggest improvements
and to generate content for review.

The extension provides these features:

Quality panel in the page module
    A compact summary at the top of :guilabel:`Content > Layout`: scores,
    issues, a SERP preview, a readability bar and pending AI changes.
    See :ref:`editor-page-module-panel`.

Content Quality backend module
    :guilabel:`Content > Content Quality` shows the full analysis of a page,
    a score trend, a heading map, link check results, suggested internal
    links and the schema.org advisor. An overview lists all analysed pages,
    worst first, and can generate AI fixes for up to 15 pages at once.
    See :ref:`editor-quality-module`.

AI fixes with review
    The AI proposes a new page title, meta description or image ALT texts.
    Proposals are stored as *pending changes* and nothing is written to the
    page until an editor applies them. See :ref:`editor-ai-fixes`.

Schema.org advisor
    Detects a suitable schema.org type for a page (for example ``Event``,
    ``NewsArticle`` or ``FAQPage``), maps page fields to schema properties
    and validates the result. After an editor approves it, the JSON-LD is
    added to the page's frontend output. See :ref:`editor-schema-advisor`.

AI text generator in CKEditor
    A :guilabel:`✦ AI` toolbar button in the rich-text editor generates text
    from a prompt, with tone, language, format and length options.
    See :ref:`editor-ckeditor`.

AI metadata for images in the file list
    A button in :guilabel:`Media > Filelist` fills missing image metadata.
    Embedded EXIF and IPTC data is read first; the AI only describes what the
    file itself does not provide. See :ref:`filelist-ai-metadata`.

..  _features:

Checks at a glance
==================

..  list-table::
    :header-rows: 1
    :widths: 20 80

    *   -   Category
        -   Checks
    *   -   Accessibility
        -   Missing or generic image ALT texts, missing H1, more than one H1,
            skipped heading levels, generic link texts ("click here",
            "read more", "hier klicken", …).
    *   -   SEO
        -   Missing, too short or too long page title and meta description,
            no links on the page, titles or meta descriptions that are 85 %
            or more similar to another page, broken links (on demand).
    *   -   Readability
        -   Flesch Reading Ease (Amstad formula for German), sentences longer
            than 25 words, pages with fewer than 50 words, reading time.
    *   -   Structured data
        -   Detected schema.org type, missing required and recommended
            properties, structural JSON-LD errors.
    *   -   AI (optional)
        -   Five improvement suggestions per page and suggested internal
            links to related pages.

Each category gets a score from 0 to 100. See :ref:`editor-scores` for how
scores are calculated.

..  _requirements:

Requirements
============

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Requirement
        -   Version / notes
    *   -   TYPO3
        -   14.0 – 14.x
    *   -   PHP
        -   8.2 or higher (as required by TYPO3 14). The ``exif`` extension
            is optional and used to read embedded image metadata.
    *   -   TYPO3 system extensions
        -   ``backend``, ``extbase``, ``fluid``, ``filelist``,
            ``filemetadata``. ``rte_ckeditor`` is optional (AI text
            generator).
    *   -   AI provider (optional)
        -   An Anthropic API key, an OpenAI API key or a reachable Ollama
            server.

..  note::

    Without an AI provider, the rule-based accessibility, SEO, readability
    and schema.org checks still run. AI features (suggestions, fixes, AI
    schema detection, text generation, image metadata) are not available.

..  _data-privacy:

Data privacy
============

When an external AI provider (Anthropic or OpenAI) is configured, the
extension sends content to that provider:

*   page title, meta description, headings and body text for analysis,
    fixes, internal link suggestions and schema detection,
*   the image file itself (base64-encoded) for image metadata generation,
*   the prompt entered in the AI text generator.

Make sure this is covered by your privacy policy and any data processing
agreement with the provider. Use Ollama if content must not leave your own
infrastructure.
