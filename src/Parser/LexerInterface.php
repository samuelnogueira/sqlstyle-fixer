<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser;

interface LexerInterface
{
    public function parseString(string $string): TokenListInterface;
}
