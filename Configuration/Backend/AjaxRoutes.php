<?php

use Woit\T3ContentQuality\Controller\AiTextGeneratorController;

return [
    't3contentquality_generate_text' => [
        'path' => '/content-quality/generate-text',
        'target' => AiTextGeneratorController::class . '::generateAction',
        'methods' => ['POST'],
    ],
];
