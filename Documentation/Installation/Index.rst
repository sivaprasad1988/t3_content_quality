..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Composer installation
=====================

..  code-block:: bash

    composer require woit/t3-content-quality

The package is available on
`Packagist <https://packagist.org/packages/woit/t3-content-quality>`__.

..  _installation-ter:

Installation from the TER
=========================

In non-Composer (classic mode) installations, open
:guilabel:`System > Extensions`, search for ``t3_content_quality`` and
install it. The system extensions ``filelist`` and ``filemetadata`` must
be active.

The extension is available in the
`TYPO3 Extension Repository <https://extensions.typo3.org/extension/t3_content_quality>`__.

..  _installation-setup:

Set up the extension
====================

Run the extension setup. It creates the database tables and adds the new
page field:

..  code-block:: bash

    vendor/bin/typo3 extension:setup -e t3_content_quality

Alternatively use :guilabel:`System > Maintenance > Analyze Database
Structure`.

The extension creates these tables:

``tx_t3contentquality_result``
    Latest analysis result per page and language.

``tx_t3contentquality_history``
    Score snapshot of every analysis run, used for the score trend.

``tx_t3contentquality_pending_fix``
    AI-generated changes waiting for review.

``tx_t3contentquality_schema``
    Approved JSON-LD per page and language.

It also adds the field ``pages.tx_t3contentquality_schema_type``
(see :ref:`configuration-page-field`).

The extension ``filemetadata`` adds its own fields to
``sys_file_metadata``, for example ``caption``, ``creator``,
``copyright`` and ``download_name``. These are needed for the file list
metadata button.

Flush the caches afterwards:

..  code-block:: bash

    vendor/bin/typo3 cache:flush

..  _installation-next:

Next steps
==========

#.  Configure an AI provider, see :ref:`configuration-extension`.
    The default provider is ``ollama``; change it if you do not run Ollama.
#.  To use the text generator, activate the RTE preset
    ``t3_content_quality`` or add the AI button to your own preset, see
    :ref:`configuration-ckeditor`.
#.  Give editors access to the module, see :ref:`admin-permissions`.
#.  Open :guilabel:`Content > Content Quality`, select a page and click
    :guilabel:`Analyse Page`. When scores appear, the installation
    works.
