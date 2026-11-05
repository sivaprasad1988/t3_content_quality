..  include:: /Includes.rst.txt

..  _known-problems:

==============
Known problems
==============

This is a beta version. The following limitations are known:

*   **German-centric texts.** The AI writes image metadata in German, the
    readability formula is the German Amstad variant, and the file list
    button tooltip is German.
*   **Duplicate content check** compares every visible page of the default
    language. On very large sites the analysis can be slow.
*   **The overview** only lists pages in the default language, and batch
    fixes are generated for the default language only.
*   **The SEO messages** recommend 30–60 characters for titles and
    120–160 for meta descriptions, while warnings are only raised below 10
    or above 70 (title) and below 70 or above 160 (description).
*   **The text generator's length limit** is an instruction to the AI and
    is not enforced.
*   **The bulk image metadata command** only selects images with an empty
    alternative text. Images that have an alternative text but miss title,
    description or caption are not selected; use the file list button for
    them. Images for which the AI returns nothing are selected again in
    every run.
*   **Approved JSON-LD** is output as approved. Editors who may edit a page
    can publish any JSON-LD for it; check it before approving.

Please report problems and feature requests to the author:
sivaprasad.s88@gmail.com.
