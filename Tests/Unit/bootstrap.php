<?php

declare(strict_types=1);

/*
 * Finds the Composer autoloader of either a standalone checkout of the
 * extension (vendor/ next to it) or of the TYPO3 project it is installed in,
 * and registers the test namespace.
 */
$candidates = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $classLoader = require $candidate;
        $classLoader->addPsr4('Woit\\T3ContentQuality\\Tests\\', __DIR__ . '/..');
        $classLoader->addPsr4('Woit\\T3ContentQuality\\', __DIR__ . '/../../Classes');
        return;
    }
}

throw new \RuntimeException('Composer autoloader not found. Run "composer install" first.');
