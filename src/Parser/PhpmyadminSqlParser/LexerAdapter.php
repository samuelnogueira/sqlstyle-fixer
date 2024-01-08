<?php

namespace Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Lexer;
use Samuelnogueira\SqlstyleFixer\Parser\LexerInterface;
use Samuelnogueira\SqlstyleFixer\Parser\TokenListInterface;

final class LexerAdapter implements LexerInterface
{
    public function parseString(string $string): TokenListInterface
    {
        // This lexer adds a DELIMITER token as the last element of the list. We are going to remove that.
        $list = (new Lexer($string))->list;
//        unset($list[$list->count - 1]);

        return new TokenListAdapter($list);
    }
}
