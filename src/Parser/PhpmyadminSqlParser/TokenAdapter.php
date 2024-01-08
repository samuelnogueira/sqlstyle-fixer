<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use Samuelnogueira\SqlstyleFixer\Parser\TokenInterface;

final class TokenAdapter implements TokenInterface
{
    private const UNION_KEYWORDS = [
        'UNION' => true,
        'UNION ALL' => true,
        'UNION DISTINCT' => true,
    ];
    private const NOT_ROOT_KEYWORDS = [
        'INTO' => true,
        'CHECK' => true,
        'ON' => true,
    ];

    public function __construct(
        private readonly Token $token
    ) {
    }

    public function isRootKeyword(): bool
    {
        // Token must be a keyword
        if ($this->token->type !== Token::TYPE_KEYWORD) {
            return false;
        }

        // Keyword most not be in the no-no list
        if (isset(self::NOT_ROOT_KEYWORDS[$this->token->keyword])) {
            return false;
        }

        // Must not be a JOIN
        if (isset(JoinKeyword::$JOINS[$this->token->keyword])) {
            return false;
        }

        return isset(Parser::$STATEMENT_PARSERS[$this->token->keyword])
            || isset(Parser::$KEYWORD_PARSERS[$this->token->keyword]);
    }

    public function isWhitespace(): bool
    {
        return $this->token->type === Token::TYPE_WHITESPACE;
    }

    public function toString(): string
    {
        return $this->token->token ?? '';
    }

    public function toUpperCase(): void
    {
        $this->token->token = strtoupper($this->token->token);
    }

    public function replaceContent(string $content): void
    {
        $this->token->token = $content;
    }

    public function firstWordLength(): int
    {
        return strlen(explode(' ', $this->token->token, 2)[0]);
    }

    public function isOpenParenthesis(): bool
    {
        return $this->token->token === '(';
    }

    public function isCloseParenthesis(): bool
    {
        return $this->token->token === ')';
    }

    public function isSelect(): bool
    {
        return $this->token->keyword === 'SELECT';
    }

    public function isUnion(): bool
    {
        return isset(self::UNION_KEYWORDS[$this->token->keyword]);
    }

    public function isKeyword(): bool
    {
        return $this->token->type === Token::TYPE_KEYWORD;
    }
}
