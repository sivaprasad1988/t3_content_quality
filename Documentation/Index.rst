..  include:: /Includes.rst.txt

..  _start:

=========================
Content Quality Assistant
=========================

:Extension key:
    t3_content_quality

:Package name:
    woit/t3-content-quality

:Version:
    |release|

:Language:
    en

:Author:
    Sivaprasad Sisupalan

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

The Content Quality Assistant checks TYPO3 pages for accessibility, SEO,
readability and structured-data problems, directly in the backend. An optional
AI provider (Anthropic, OpenAI or a local Ollama instance) suggests
improvements, proposes page titles, meta descriptions and image ALT texts for
review, detects a schema.org type for each page, writes texts in the rich-text
editor and fills missing image metadata in the file list.

----

**Table of Contents:**

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension does, its features and system requirements.

    ..  card:: :ref:`Installation <installation>`

        Install with Composer or from the TER and set up the database.

    ..  card:: :ref:`Configuration <configuration>`

        AI provider settings, the rich-text editor button, page fields and
        the frontend middleware.

    ..  card:: :ref:`Editor manual <editor-manual>`

        Using the quality panel, the Content Quality module, AI fixes, the
        schema.org advisor, the AI text generator and the file metadata button.

    ..  card:: :ref:`Administration <administration>`

        Permissions, database tables, API keys, Ollama and logging.

    ..  card:: :ref:`Developer <developer>`

        Architecture, services, routes and how to extend the extension.

    ..  card:: :ref:`Known problems <known-problems>`

        Current limitations of this version.

    ..  card:: :ref:`Changelog <changelog>`

        Changes per version.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    EditorManual/Index
    Administration/Index
    Developer/Index
    KnownProblems/Index
    Changelog/Index
