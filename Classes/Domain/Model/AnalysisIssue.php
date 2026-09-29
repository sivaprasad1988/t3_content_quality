<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Domain\Model;

class AnalysisIssue
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    public const CATEGORY_ACCESSIBILITY = 'accessibility';
    public const CATEGORY_SEO = 'seo';
    public const CATEGORY_READABILITY = 'readability';
    public const CATEGORY_SCHEMA = 'schema';

    public function __construct(
        public readonly string $category,
        public readonly string $severity,
        public readonly string $message,
        public readonly ?string $suggestion = null,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'severity' => $this->severity,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
