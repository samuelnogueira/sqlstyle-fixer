<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests\Lexer\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;
use Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser\TokenListAdapter;
use PHPUnit\Framework\TestCase;

final class TokenListAdapterTest extends TestCase
{
    public function testFirstNonWhitespaceWithAnEmptyList(): void
    {
        $subject = new TokenListAdapter(new TokensList());

        self::assertNull($subject->firstNonWhitespace());
    }

    public function testFirstNonWhitespaceWithStartingWhitespaces(): void
    {
        $nonWhitespaceToken = new Token('-- test', Token::TYPE_COMMENT);
        $subject = new TokenListAdapter(
            new TokensList([
                new Token(' ', Token::TYPE_WHITESPACE),
                new Token(' ', Token::TYPE_WHITESPACE),
                $nonWhitespaceToken,
            ]),
        );

        self::assertEquals('-- test', $subject->firstNonWhitespace()?->toString());
    }
}
