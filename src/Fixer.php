<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

/**
 * @api
 * @immutable
 */
final class Fixer
{
    public function fixString(string $sql): string
    {
        return $sql;
    }
}
