<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRules([
        '@PER-CS2.0' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__),
    );
