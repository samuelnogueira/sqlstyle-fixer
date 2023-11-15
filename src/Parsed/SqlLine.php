<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parsed;

use Safe\Exceptions\PcreException;

/**
 * @internal
 */
final class SqlLine
{
    /**
     * @param non-empty-string $line
     */
    private function __construct(private readonly string $line)
    {
    }

    public function getLine(): string
    {
        return $this->line;
    }

    /**
     * @return int<0, max>|null
     */
    public function findRiverOffset(): int|null
    {
        if (preg_match('/^(\s+[(A-Z]+)/', $this->line, $matches) === 0) {
            return null;
        }

        return strlen($matches[1]);
    }

    /**
     * @return iterable<self>
     */
    public static function allFromSqlString(string $sql): iterable
    {
        foreach (explode(PHP_EOL, trim($sql, '; ' . PHP_EOL)) as $line) {
            if ($line === '') {
                // Ignore empty lines.
                continue;
            }

            yield new self($line);
        }
    }
}
