<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Schema;

final class SchemaTypes
{
    public const EVENT = 'Event';
    public const NEWS_ARTICLE = 'NewsArticle';
    public const TOURIST_ATTRACTION = 'TouristAttraction';
    public const LOCAL_BUSINESS = 'LocalBusiness';
    public const ORGANIZATION = 'Organization';
    public const FAQ_PAGE = 'FAQPage';
    public const JOB_POSTING = 'JobPosting';
    public const PRODUCT = 'Product';
    public const WEB_PAGE = 'WebPage';

    public const ALL = [
        self::EVENT,
        self::NEWS_ARTICLE,
        self::TOURIST_ATTRACTION,
        self::LOCAL_BUSINESS,
        self::ORGANIZATION,
        self::FAQ_PAGE,
        self::JOB_POSTING,
        self::PRODUCT,
    ];

    /** @var array<string,string[]> Required schema.org properties per type */
    public const REQUIRED = [
        self::EVENT => ['name', 'startDate'],
        self::NEWS_ARTICLE => ['headline', 'datePublished', 'author'],
        self::TOURIST_ATTRACTION => ['name'],
        self::LOCAL_BUSINESS => ['name'],
        self::ORGANIZATION => ['name'],
        self::FAQ_PAGE => ['mainEntity'],
        self::JOB_POSTING => ['title', 'datePosted', 'hiringOrganization', 'jobLocation'],
        self::PRODUCT => ['name'],
        self::WEB_PAGE => ['name'],
    ];

    /** @var array<string,string[]> Recommended schema.org properties per type */
    public const RECOMMENDED = [
        self::EVENT => ['endDate', 'location', 'description', 'image', 'url', 'organizer'],
        self::NEWS_ARTICLE => ['dateModified', 'description', 'image', 'publisher', 'url'],
        self::TOURIST_ATTRACTION => ['description', 'image', 'url', 'address', 'telephone'],
        self::LOCAL_BUSINESS => ['description', 'address', 'telephone', 'url', 'openingHours', 'image'],
        self::ORGANIZATION => ['description', 'url', 'logo', 'contactPoint', 'sameAs'],
        self::FAQ_PAGE => ['description', 'url'],
        self::JOB_POSTING => ['description', 'validThrough', 'employmentType', 'baseSalary'],
        self::PRODUCT => ['description', 'image', 'brand', 'offers', 'url'],
        self::WEB_PAGE => ['description', 'url', 'dateModified'],
    ];
}
