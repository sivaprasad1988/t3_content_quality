CREATE TABLE tx_t3contentquality_result (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    page_uid int(11) DEFAULT '0' NOT NULL,
    language_uid int(11) DEFAULT '0' NOT NULL,
    overall_score smallint(3) DEFAULT '0' NOT NULL,
    accessibility_score smallint(3) DEFAULT '0' NOT NULL,
    seo_score smallint(3) DEFAULT '0' NOT NULL,
    readability_score smallint(3) DEFAULT '0' NOT NULL,
    schema_score smallint(3) DEFAULT '0' NOT NULL,
    issues_json mediumtext,
    suggestions_json mediumtext,
    analyzed_at int(11) DEFAULT '0' NOT NULL,
    model_used varchar(100) DEFAULT '' NOT NULL,
    provider_used varchar(50) DEFAULT '' NOT NULL,
    metadata_json mediumtext,

    PRIMARY KEY (uid),
    KEY page_uid (page_uid),
    KEY page_language (page_uid, language_uid)
);

CREATE TABLE tx_t3contentquality_history (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    page_uid int(11) DEFAULT '0' NOT NULL,
    language_uid int(11) DEFAULT '0' NOT NULL,
    overall_score smallint(3) DEFAULT '0' NOT NULL,
    accessibility_score smallint(3) DEFAULT '0' NOT NULL,
    seo_score smallint(3) DEFAULT '0' NOT NULL,
    readability_score smallint(3) DEFAULT '0' NOT NULL,
    schema_score smallint(3) DEFAULT '0' NOT NULL,
    analyzed_at int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY page_language (page_uid, language_uid)
);

CREATE TABLE tx_t3contentquality_pending_fix (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    page_uid int(11) DEFAULT '0' NOT NULL,
    language_uid int(11) DEFAULT '0' NOT NULL,
    fix_type varchar(20) DEFAULT '' NOT NULL,
    target_ref int(11) DEFAULT '0' NOT NULL,
    old_value mediumtext,
    new_value mediumtext,
    created_at int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY page_language (page_uid, language_uid)
);

CREATE TABLE tx_t3contentquality_schema (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    page_uid int(11) DEFAULT '0' NOT NULL,
    language_uid int(11) DEFAULT '0' NOT NULL,
    schema_type varchar(50) DEFAULT '' NOT NULL,
    jsonld_json mediumtext,
    approved tinyint(1) DEFAULT '0' NOT NULL,
    approved_at int(11) DEFAULT '0' NOT NULL,
    approved_by int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY page_uid (page_uid),
    KEY page_language (page_uid, language_uid)
);

CREATE TABLE pages (
    tx_t3contentquality_schema_type varchar(50) DEFAULT '' NOT NULL
);
