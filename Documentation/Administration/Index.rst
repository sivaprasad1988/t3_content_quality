..  include:: /Includes.rst.txt

..  _administration:

==============
Administration
==============

..  contents::
    :local:
    :depth: 2

..  _admin-permissions:

Backend user permissions
========================

..  _admin-permissions-module:

Content Quality module
----------------------

Grant access in :guilabel:`Administration > Backend Users` in the backend
user group, tab :guilabel:`Access Rights`, module
:guilabel:`Content > Content Quality`.

The quality panel in :guilabel:`Content > Layout` is shown to every user who
can open the page module. It does not need the module permission.

..  _admin-permissions-filelist:

File list metadata button
-------------------------

The button is only shown for files that are indexed and for which the user
has the ``editMeta`` permission. Grant it in the backend user group, tab
:guilabel:`Mounts and Workspaces`, :guilabel:`File Operation Permissions`.

..  _admin-permissions-fixes:

Page permissions
----------------

All actions respect the backend user's web mounts, page permissions,
table permissions and language access:

..  list-table::
    :header-rows: 1
    :widths: 45 55

    *   -   Action
        -   Required permission
    *   -   See the panel, the module page view and analysis results;
            analyse a page; check links; AI schema detection
        -   Show page
    *   -   Generate, apply or discard title and meta description fixes;
            approve or remove the schema
        -   Edit page, and modify permission for the ``pages`` table
    *   -   Generate, apply or discard ALT text fixes
        -   Edit content on the page, and modify permission for the
            ``sys_file_reference`` table
    *   -   All pages overview, batch fixes, CSV export
        -   Only pages the user may show (or edit, for fixes) are included
    *   -   File list metadata button
        -   ``editMeta`` on the file

Applied fixes are written with the TYPO3 DataHandler. Changes appear in the
record history, respect workspaces and clear the page cache like a normal
edit. For a translated page, the change is written to the translation.

..  _admin-database:

Database tables
===============

..  _admin-database-result:

tx_t3contentquality_result
--------------------------

The latest analysis result per page and language. A new analysis replaces
the previous row.

..  rst-class:: dl-parameters

page_uid, language_uid
    Page and language of the result.

overall_score, accessibility_score, seo_score, readability_score, schema_score
    Scores from 0 to 100.

issues_json
    JSON list of issues with ``category``, ``severity``, ``message`` and
    ``suggestion``.

suggestions_json
    JSON list of AI suggestions.

metadata_json
    Page facts collected during the analysis, for example title, meta
    description, headings, Flesch score, link check results and schema
    detection details.

analyzed_at
    Unix timestamp of the analysis.

model_used, provider_used
    AI model and provider used.

..  _admin-database-history:

tx_t3contentquality_history
---------------------------

One row with the scores of every analysis run. Used for the score trend.
Rows are never deleted automatically.

..  _admin-database-pending:

tx_t3contentquality_pending_fix
-------------------------------

AI proposals waiting for review. ``fix_type`` is ``title``,
``description`` or ``alt_text``. For ``alt_text``, ``target_ref`` is the
UID of the ``sys_file_reference`` record. Rows are deleted when they are
applied or discarded. If the DataHandler rejects a change (for example
missing permissions), the row stays pending.

..  _admin-database-schema:

tx_t3contentquality_schema
--------------------------

Approved JSON-LD per page and language (``schema_type``, ``jsonld_json``,
``approved``, ``approved_at``). Every row with ``approved = 1`` is added to
the frontend output of its page.

..  _admin-maintenance:

Maintenance
===========

Remove the history of old analysis runs, for example everything older than
one year:

..  code-block:: sql

    DELETE FROM tx_t3contentquality_history
    WHERE analyzed_at < UNIX_TIMESTAMP() - 365 * 86400;

Remove all analysis results (pages then show :guilabel:`Not analysed yet.`):

..  code-block:: sql

    TRUNCATE tx_t3contentquality_result;

..  warning::

    Emptying ``tx_t3contentquality_schema`` removes the JSON-LD from the
    frontend of all pages immediately.

Pages are only analysed when an editor starts the analysis. There is no
scheduler task or console command for batch analysis in this version.

..  _admin-api-keys:

API keys
========

API keys entered in the extension configuration are stored in plain text in
:file:`config/system/settings.php`. Set them from environment variables
instead, see :ref:`configuration-api-keys-env`.

Further recommendations:

*   Allow outgoing HTTPS connections only to the provider you use:
    ``api.anthropic.com`` or ``api.openai.com``.
*   Set a spending limit in the provider's dashboard.

..  _admin-network:

Outgoing HTTP requests
======================

Besides the AI provider, the extension makes these HTTP requests from the
web server:

*   During every analysis, a ``GET`` request to the frontend URL of the page
    (timeout 10 seconds) to read the rendered headings. The page must be
    reachable from the server; otherwise the headings are taken from the
    database.
*   When :guilabel:`Check Links` is clicked, ``HEAD`` requests to up to 30
    links of the page.

The requests use the TYPO3 HTTP client settings in
``$GLOBALS['TYPO3_CONF_VARS']['HTTP']`` (proxy, SSL verification).

..  _admin-ollama:

Ollama
======

The extension connects to the Ollama server set in
:confval:`t3cq-ollamaUrl` (default ``http://ollama:11434``, the host name
of an ``ollama`` container in a Docker Compose or DDEV setup).

Before each analysis request, the extension checks whether Ollama is
reachable. If it is not, the AI part is skipped and a warning is logged.

Pull the configured model on the Ollama server:

..  code-block:: bash

    ollama pull llama3.2

For image descriptions in the file list, pull a model that supports
images:

..  code-block:: bash

    ollama pull llava

..  _admin-logging:

Logging
=======

The extension logs through the TYPO3 logging API. Logger names start with
``Woit.T3ContentQuality``.

..  list-table::
    :header-rows: 1
    :widths: 15 85

    *   -   Level
        -   Messages
    *   -   error
        -   AI analysis failed, AI image metadata generation failed, AI text
            generation failed, Batch fix generation failed for page,
            Internal link suggestion failed
    *   -   warning
        -   AI analysis skipped: Ollama not reachable, AI fix skipped: no API
            key configured, AI schema detection failed, AI schema detection
            skipped: Ollama not reachable, Pending fix rejected by
            DataHandler, Pending fix skipped: no page record for language
    *   -   info
        -   File metadata updated, Image too large for AI vision

By default TYPO3 writes messages of level ``warning`` and higher to
:file:`var/log/typo3_*.log`. To also log ``info`` messages of this
extension:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Woit']['T3ContentQuality']['writerConfiguration'] = [
        \Psr\Log\LogLevel::INFO => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFileInfix' => 't3_content_quality',
            ],
        ],
    ];
