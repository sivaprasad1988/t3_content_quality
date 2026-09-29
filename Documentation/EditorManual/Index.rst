..  include:: /Includes.rst.txt

..  _editor-manual:

=============
Editor manual
=============

..  contents::
    :local:
    :depth: 2

..  _editor-page-module-panel:

Quality panel in the page module
================================

When you open a page in :guilabel:`Content > Layout`, the
:guilabel:`Content Quality` panel is shown above the page content.

..  figure:: /Images/PageModulePanel.png
    :alt: Content Quality panel in the page module with scores, SERP preview and accessibility issues
    :class: with-shadow

    The quality panel with the details opened.

A page is not analysed automatically. If the page has never been analysed,
the panel shows :guilabel:`Not analysed yet.` Click :guilabel:`Analyse Page`
to run the checks. Later, click :guilabel:`Reanalyse` after you have changed
the page.

After an analysis the panel shows:

*   the overall score and the scores for accessibility, SEO, readability and
    schema,
*   a :guilabel:`SERP Preview` of the title and meta description with
    character counters (60 characters for the title, 160 for the
    description),
*   an accessibility breakdown,
*   the :guilabel:`Flesch Reading Ease` bar,
*   the list of issues with category, severity, message and suggestion,
*   AI suggestions, if an AI provider is configured.

Issues that the AI can fix have a :guilabel:`✨ Fix` button in the last
column. See :ref:`editor-ai-fixes`.

..  _editor-scores:

How scores are calculated
-------------------------

Every issue has a severity. Each category starts at 100 and loses points per
issue:

..  list-table::
    :header-rows: 1

    *   -   Severity
        -   Points deducted
    *   -   error
        -   20
    *   -   warning
        -   10
    *   -   info
        -   3

A category score never drops below 0. The **overall score** is the average
of the accessibility, SEO and readability scores.

The **schema score** is calculated separately and is not part of the overall
score: it starts at 100 and loses 25 points for every missing required
schema.org property and 5 points for every missing recommended property.

..  _editor-checks:

What is checked
---------------

Accessibility
    *   Images without ALT text, or with a generic ALT text such as "image",
        "photo", "bild" or "foto".
    *   No headings, no H1, more than one H1, skipped heading levels
        (for example H2 followed by H4).
    *   Generic link texts such as "click here", "read more", "more",
        "hier klicken" or "mehr erfahren".

    Headings are read from the rendered frontend page when it can be
    fetched. Otherwise they are derived from the content elements' header
    fields.

SEO
    *   Page title missing (error), shorter than 10 or longer than 70
        characters (warning).
    *   Meta description missing (error), shorter than 70 or longer than 160
        characters (warning).
    *   No links on the page (info).
    *   Title or meta description at least 85 % similar to another page
        (warning).

Readability
    *   Flesch Reading Ease (Amstad formula for German texts): below 30 is a
        warning, below 60 is an info.
    *   Sentences longer than 25 words: info, or warning if there are more
        than five.
    *   Less than 50 words of text (info).
    *   The estimated reading time is shown (200 words per minute).

..  _editor-ai-fixes:

Fixing issues with AI
=====================

The AI can propose a new **page title**, a new **meta description** and
**ALT texts** for images without one.

#.  Click :guilabel:`✨ Fix` next to an issue in the page module panel, or
    :guilabel:`Fix: Page Title`, :guilabel:`Fix: Meta Description` or
    :guilabel:`Fix: Image ALT Text` in the Content Quality module.
#.  The AI generates a proposal. Nothing is written to the page yet.
#.  The proposal appears under :guilabel:`✨ AI Suggested Changes (not saved
    yet)` in the panel, or :guilabel:`Pending AI Changes` in the module,
    with the old and the new value side by side.
#.  Click :guilabel:`Apply` to write the change, or :guilabel:`Discard` to
    delete the proposal. In the module you can apply or discard all or only
    selected changes.
#.  After applying, the page is analysed again.

ALT texts are written to the image reference of the content element
(``sys_file_reference``), not to the file's global metadata. Up to ten images
are handled in one request.

..  note::

    Check the proposals before you apply them. AI output can be wrong or
    unsuitable for your audience.

..  _editor-quality-module:

Content Quality module
======================

Open :guilabel:`Content > Content Quality` and select a page in the page
tree.

..  _editor-quality-module-page:

Page view
---------

..  figure:: /Images/ModulePageView.png
    :alt: Content Quality module showing the overall score, category scores, score trend and issue list
    :class: with-shadow

    Page view of the Content Quality module.

The page view shows everything from the panel in more detail, plus:

:guilabel:`Score Trend`
    The scores of the previous analysis runs of this page.

:guilabel:`Heading Map`
    The heading structure of the page. Missing levels and duplicate H1
    headings are marked.

:guilabel:`Link Check`
    Click :guilabel:`Check Links` to test the links on the page (internal
    and external, without anchors, ``mailto:`` and ``tel:`` links). Up to 30
    links are checked with a ``HEAD`` request and a timeout of five seconds.
    Links that answer with status 400 or higher are reported as broken.

:guilabel:`Suggested Internal Links`
    If AI is enabled, the AI suggests related pages of the same site that
    this page could link to.

:guilabel:`Structured Data (Schema.org)`
    The schema.org advisor, see :ref:`editor-schema-advisor`.

Click :guilabel:`Run Analysis Again` to refresh the result.

..  _editor-quality-module-overview:

All pages overview
------------------

Click :guilabel:`All Pages Overview` to list all analysed pages of the
default language, worst score first.

..  figure:: /Images/AllPagesOverview.png
    :alt: All pages overview listing analysed pages with scores and the batch fix controls
    :class: with-shadow

    All pages overview with the batch fix controls. The overview also shows how many pages
have not been analysed yet. Analyse them individually to include them.

To fix several pages at once:

#.  Select the pages in the list.
#.  Choose the fix type: page title, meta description or image ALT text.
#.  Click :guilabel:`Generate Fixes for Selected (max 15)`.
#.  Review the proposals in :guilabel:`Review Batch AI Changes` and apply or
    discard them.

If more than 15 pages are selected, only the first 15 are processed. Run the
action again for the rest.

..  _editor-schema-advisor:

Schema.org advisor
==================

The schema.org advisor describes the page for search engines using
`JSON-LD <https://json-ld.org/>`__.

..  figure:: /Images/SchemaAdvisor.png
    :alt: Schema.org advisor with detected type, schema score and editable JSON-LD preview
    :class: with-shadow

    Schema.org advisor with the editable JSON-LD preview.

Detected type
    The type is determined in this order:

    #.  The :guilabel:`Schema Type (Structured Data)` field in the page
        properties, if set.
    #.  Content elements of known extensions on the page, for example
        ``tx_news`` (``NewsArticle``), event extensions (``Event``) or job
        extensions (``JobPosting``).
    #.  Keywords in the title, meta description and abstract, for example
        "FAQ" (``FAQPage``), "Veranstaltung" or "event" (``Event``),
        "Museum" (``TouristAttraction``), "Öffnungszeiten" or "opening
        hours" (``LocalBusiness``).
    #.  Otherwise ``WebPage``.

    The label shows whether the type was set manually, detected by rules
    (:guilabel:`Rule-based`) or by the AI (:guilabel:`AI-detected`).

Mapped fields and validation
    The schema.org properties filled from the page, the missing required and
    recommended properties, and structural errors in the JSON-LD.

:guilabel:`Detect with AI`
    Lets the AI choose the type and fill the properties from the page
    content.

:guilabel:`JSON-LD Preview (editable before approving)`
    The generated JSON-LD. You can edit it before approving.

:guilabel:`Approve & Activate`
    Saves the JSON-LD. From now on it is included in the frontend output of
    this page (:guilabel:`Approved & Live`).

:guilabel:`Remove Approved Schema`
    Deletes the approved JSON-LD. It is no longer included in the frontend.

:guilabel:`Export Audit CSV`
    Downloads a CSV file with page ID, detected type, schema score, overall
    score, approval status and analysis date for all analysed pages.

..  _editor-schema-override:

Overriding the schema type
--------------------------

#.  Open the page properties and switch to the :guilabel:`Metadata` tab.
#.  Select a type in :guilabel:`Schema Type (Structured Data)`.
#.  Save the page.
#.  Analyse the page again and approve the new JSON-LD.

..  _editor-ckeditor:

AI text generator in the rich-text editor
=========================================

If your integrator has added it (see :ref:`configuration-ckeditor`), the
rich-text editor toolbar contains a :guilabel:`✦ AI` button.

..  figure:: /Images/AiTextGenerator.png
    :alt: AI text generator dialog with prompt, tone, language, format and length options
    :class: with-shadow

    The AI text generator dialog.

#.  Place the cursor where the text should go.
#.  Click :guilabel:`✦ AI`.
#.  Describe the text you want, for example *"Two sentences introducing our
    summer workshop for children"*.
#.  Choose the options:

    Tone
        Neutral, Formal, Friendly or Professional.

    Language
        Auto-detect, German, English, French or Spanish. With
        *Auto-detect*, the language of the record being edited is used.

    Format
        Paragraph(s), Bullet points or Headline only.

    Maximum length
        50 to 3000 characters (default 500). The limit is passed to the AI
        as an instruction; the answer is not cut off.

#.  Click :guilabel:`Generate` and wait for the result.
#.  Click :guilabel:`Insert into editor` to insert the text, or
    :guilabel:`Cancel` to close the dialog.

..  _filelist-ai-metadata:

AI metadata for images in the file list
=======================================

In :guilabel:`Media > Filelist`, image files that miss one of the metadata
fields :guilabel:`Alternative text`, :guilabel:`Title`,
:guilabel:`Description` or :guilabel:`Caption` get an additional button. Its
tooltip lists the missing fields. The button disappears once all four
fields are filled.

..  figure:: /Images/FilelistButton.png
    :alt: File list rows with the AI metadata button as first control
    :class: with-shadow

    The AI metadata button (first icon in the :guilabel:`Control` column).

The button is only shown if you may edit the file's metadata.

What happens when you click it:

#.  **Embedded metadata is read first.** No AI is used for this step.

    ..  list-table::
        :header-rows: 1

        *   -   Embedded field
            -   Metadata field
        *   -   EXIF ``Artist``, IPTC By-line (2#080)
            -   Creator
        *   -   EXIF ``Copyright``, IPTC Copyright Notice (2#116)
            -   Copyright
        *   -   EXIF ``ImageDescription``, IPTC Caption/Abstract (2#120)
            -   Description
        *   -   EXIF ``DocumentName``, IPTC Object Name (2#005), IPTC
                Headline (2#105)
            -   Title
        *   -   EXIF ``Software``
            -   Creator tool
        *   -   IPTC Keywords (2#025)
            -   Keywords

    Existing values are never overwritten. The download name is set to the
    file name without extension if it is empty.

#.  **The AI describes the image.** Alternative text, title, description
    and caption that are still empty are generated by the AI from the image
    content, in German.
#.  **You review the result.** The metadata form of the file opens with the
    new values. Check them and save.

..  important::

    Creator, publisher, source and copyright can not be recognised from the
    image. Fill them in yourself if the file does not contain them.

Limits:

*   The AI is only used for JPEG, PNG, GIF and WebP files up to 5 MB.
    Embedded metadata is read for all image files.
*   With Ollama, the configured model must support images (for example
    ``llava``), see :confval:`t3cq-ollamaModel`.
