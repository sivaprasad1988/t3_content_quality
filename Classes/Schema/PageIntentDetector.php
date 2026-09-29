<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Schema;

class PageIntentDetector
{
    /**
     * Detects schema type from page data.
     * Priority: manual override > CType hints > keyword matching.
     */
    public function detect(array $pageData): ?string
    {
        // Manual override set by editor in page properties
        $override = trim((string)($pageData['schema_type_override'] ?? ''));
        if ($override !== '' && in_array($override, SchemaTypes::ALL, true)) {
            return $override;
        }

        // CType-based detection (most reliable — extension-installed content elements)
        foreach ($pageData['ctypes'] ?? [] as $ctype) {
            $ctype = strtolower((string)$ctype);
            if (preg_match('/^tx_(sfeventmgt|events|bfevents|cal|calendarize|eventmgt)/', $ctype)) {
                return SchemaTypes::EVENT;
            }
            if (str_starts_with($ctype, 'tx_news')) {
                return SchemaTypes::NEWS_ARTICLE;
            }
            if (preg_match('/^tx_(jobs|dmmjobcontrol|jobapplication|positioning|staffjobs)/', $ctype)) {
                return SchemaTypes::JOB_POSTING;
            }
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            $pageData['page_title'] ?? '',
            $pageData['meta_description'] ?? '',
            $pageData['abstract'] ?? '',
        ])));

        if ($haystack === '') {
            // No title/description at all — can't generate meaningful schema
            return null;
        }

        if (preg_match('/(*UCP)\bfaq\b|frequently asked|häufig.{0,10}frag|fragen.{0,10}antwort/u', $haystack)) {
            return SchemaTypes::FAQ_PAGE;
        }

        if (preg_match('/(*UCP)\b(stelle[n]?|karriere|bewerbung|stellenanzeige|stellenausschreibung|we.re hiring|job offer)\b/u', $haystack)) {
            return SchemaTypes::JOB_POSTING;
        }

        if (preg_match('/(*UCP)\b(veranstaltung|konzert|concert|workshop|seminar|messe|kongress|ausstellung|exhibition|tagung)\b|(?<!\w)event(?!\w)/u', $haystack)) {
            return SchemaTypes::EVENT;
        }

        if (preg_match('/(*UCP)\b(pressemitteilung|press release|meldung|nachricht)\b|\bnews\b/u', $haystack)) {
            return SchemaTypes::NEWS_ARTICLE;
        }

        if (preg_match('/(*UCP)\b(museum|galerie|gallery|denkmal|monument|sehenswürdigkeit|zoo|schloss|castle|kloster|monastery)\b/u', $haystack)) {
            return SchemaTypes::TOURIST_ATTRACTION;
        }

        if (preg_match('/(*UCP)\b(restaurant|café|cafe|hotel|shop|laden|öffnungszeiten|opening hours|salon|kiosk)\b/u', $haystack)) {
            return SchemaTypes::LOCAL_BUSINESS;
        }

        if (preg_match('/(*UCP)\b(über uns|about us|organisation|organization|verein|verband|gesellschaft|unternehmen)\b/u', $haystack)) {
            return SchemaTypes::ORGANIZATION;
        }

        if (preg_match('/(*UCP)\b(produkt|kaufen|preis)\b|\bproduct\b|\bbuy\b|\bprice\b/u', $haystack)) {
            return SchemaTypes::PRODUCT;
        }

        // No specific type matched — fall back to WebPage (valid for any page with a title)
        return SchemaTypes::WEB_PAGE;
    }
}
