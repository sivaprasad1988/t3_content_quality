<?php

declare(strict_types=1);

defined('TYPO3') or die();

// RTE preset with the AI text generator button (core default toolbar + "✦ AI").
// Not activated automatically; see Documentation/Configuration "AI button in the rich-text editor".
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['t3_content_quality'] ??= 'EXT:t3_content_quality/Configuration/RTE/Default.yaml';
