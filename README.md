# Content Quality Assistant (`t3_content_quality`)

[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)

Checks TYPO3 pages for accessibility, SEO, readability and structured-data
problems directly in the backend. An optional AI provider (Anthropic, OpenAI or
a local Ollama server) suggests improvements, proposes page titles, meta
descriptions and ALT texts for review, detects schema.org types, writes texts in
CKEditor and fills missing image metadata in the file list.

|                  | URL                                                             |
|------------------|-----------------------------------------------------------------|
| **TER**          | https://extensions.typo3.org/extension/t3_content_quality       |
| **Packagist**    | https://packagist.org/packages/woit/t3-content-quality          |
| **Docs**         | https://docs.typo3.org/p/woit/t3-content-quality/main/en-us/    |

## Features

- Quality panel in **Content > Layout** with scores, issues and SERP preview
- **Content > Content Quality** module: score trend, heading map, link check,
  internal link suggestions, all-pages overview
- AI fixes for page title, meta description and image ALT texts – stored as
  pending changes, nothing is written until an editor applies them
- Schema.org advisor with editable JSON-LD, approval workflow and frontend
  output via PSR-15 middleware
- CKEditor 5 AI text generator
- AI image metadata in **Media > Filelist**, using EXIF/IPTC data first

Rule-based checks work without any AI provider.

## Installation

```bash
composer require woit/t3-content-quality
vendor/bin/typo3 extension:setup -e t3_content_quality
```

Then configure the AI provider in **System > Settings > Extension
Configuration > t3_content_quality**.

## Requirements

- TYPO3 14
- PHP 8.2+
- System extensions `filelist`, `filemetadata`; optionally `rte_ckeditor`

## Tests

```bash
composer install
composer test:unit
```

## Documentation

See [`Documentation/`](Documentation/Index.rst) or the rendered version on
docs.typo3.org. Render locally with:

```bash
docker run --rm --pull always -v $(pwd):/project \
    ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
```

## License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt).
