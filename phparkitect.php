<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $config->add(
        ClassSet::fromDir(__DIR__ . '/src'),
        Rule::allClasses()
            ->that(new NotResideInTheseNamespaces('Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser'))
            ->should(new NotDependsOnTheseNamespaces('PhpMyAdmin\SqlParser'))
            ->because('we do not want to be vendor locked')
    );
};
