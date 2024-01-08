<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Lexer;
use Samuelnogueira\SqlstyleFixer\Parser\LexerInterface;
use Samuelnogueira\SqlstyleFixer\Parser\TokenListInterface;

final class LexerAdapter implements LexerInterface
{
    public function parseString(string $string): TokenListInterface
    {
        return new TokenListAdapter((new Lexer($string))->list);
    }
}
