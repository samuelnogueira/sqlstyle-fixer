<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Lexer;
use Samuelnogueira\SqlstyleFixer\Lexer\LexerInterface;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenListInterface;

final class LexerAdapter implements LexerInterface
{
    public function parseString(string $string): TokenListInterface
    {
        return new TokenListAdapter((new Lexer($string))->list);
    }
}
