<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Schema;

class SchemaMapper
{
    public function map(string $schemaType, array $pageData): array
    {
        return match ($schemaType) {
            SchemaTypes::EVENT => $this->mapEvent($pageData),
            SchemaTypes::NEWS_ARTICLE => $this->mapNewsArticle($pageData),
            SchemaTypes::TOURIST_ATTRACTION => $this->mapTouristAttraction($pageData),
            SchemaTypes::LOCAL_BUSINESS => $this->mapLocalBusiness($pageData),
            SchemaTypes::ORGANIZATION => $this->mapOrganization($pageData),
            SchemaTypes::FAQ_PAGE => $this->mapFaqPage($pageData),
            SchemaTypes::JOB_POSTING => $this->mapJobPosting($pageData),
            SchemaTypes::PRODUCT => $this->mapProduct($pageData),
            default => $this->mapWebPage($pageData),
        };
    }

    private function base(array $pageData): array
    {
        $mapped = [];
        $this->setIfNotEmpty($mapped, 'name', $pageData['page_title'] ?? '');
        $this->setIfNotEmpty($mapped, 'description', $pageData['meta_description'] ?? '');
        $this->setIfNotEmpty($mapped, 'url', $pageData['page_url'] ?? '');
        if (!empty($pageData['images'][0]['url'])) {
            $mapped['image'] = $pageData['images'][0]['url'];
        }
        return $mapped;
    }

    private function mapEvent(array $pageData): array
    {
        $mapped = $this->base($pageData);
        // startDate and endDate not available from standard page fields; left missing to trigger validation
        return $mapped;
    }

    private function mapNewsArticle(array $pageData): array
    {
        $mapped = [];
        $this->setIfNotEmpty($mapped, 'headline', $pageData['page_title'] ?? '');
        $this->setIfNotEmpty($mapped, 'description', $pageData['meta_description'] ?? '');
        $this->setIfNotEmpty($mapped, 'url', $pageData['page_url'] ?? '');
        if (!empty($pageData['crdate'])) {
            $mapped['datePublished'] = (new \DateTimeImmutable('@' . (int)$pageData['crdate']))->format('c');
        }
        if (!empty($pageData['tstamp'])) {
            $mapped['dateModified'] = (new \DateTimeImmutable('@' . (int)$pageData['tstamp']))->format('c');
        }
        if (!empty($pageData['author'])) {
            $mapped['author'] = ['@type' => 'Person', 'name' => $pageData['author']];
        }
        if (!empty($pageData['images'][0]['url'])) {
            $mapped['image'] = $pageData['images'][0]['url'];
        }
        return $mapped;
    }

    private function mapTouristAttraction(array $pageData): array
    {
        return $this->base($pageData);
    }

    private function mapLocalBusiness(array $pageData): array
    {
        // address, telephone, openingHours require extension or custom fields
        return $this->base($pageData);
    }

    private function mapOrganization(array $pageData): array
    {
        $mapped = $this->base($pageData);
        if (!empty($pageData['images'][0]['url'])) {
            $mapped['logo'] = $pageData['images'][0]['url'];
            unset($mapped['image']);
        }
        return $mapped;
    }

    private function mapFaqPage(array $pageData): array
    {
        // mainEntity (Q&A pairs) requires structured content extraction beyond standard fields
        return $this->base($pageData);
    }

    private function mapJobPosting(array $pageData): array
    {
        $mapped = [];
        $this->setIfNotEmpty($mapped, 'title', $pageData['page_title'] ?? '');
        $this->setIfNotEmpty($mapped, 'description', $pageData['meta_description'] ?? '');
        $this->setIfNotEmpty($mapped, 'url', $pageData['page_url'] ?? '');
        if (!empty($pageData['crdate'])) {
            $mapped['datePosted'] = (new \DateTimeImmutable('@' . (int)$pageData['crdate']))->format('c');
        }
        // hiringOrganization and jobLocation require custom fields
        return $mapped;
    }

    private function mapProduct(array $pageData): array
    {
        return $this->base($pageData);
    }

    private function mapWebPage(array $pageData): array
    {
        $mapped = $this->base($pageData);
        if (!empty($pageData['tstamp'])) {
            $mapped['dateModified'] = (new \DateTimeImmutable('@' . (int)$pageData['tstamp']))->format('c');
        }
        return $mapped;
    }

    private function setIfNotEmpty(array &$target, string $key, string $value): void
    {
        if (trim($value) !== '') {
            $target[$key] = $value;
        }
    }
}
