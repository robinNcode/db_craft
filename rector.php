<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/src',
        ])
        // uncomment to reach your current PHP version
        ->withPhpSets()
        ->withTypeCoverageLevel(0)
        ->withDeadCodeLevel(0)
        ->withCodeQualityLevel(0);
} catch (InvalidConfigurationException $e) {
    return throw new RuntimeException(
        'Rector configuration error: '.$e->getMessage(),
        $e->getCode(),
        $e
    );
}
