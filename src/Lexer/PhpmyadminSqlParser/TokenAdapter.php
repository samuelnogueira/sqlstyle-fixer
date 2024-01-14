<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser;

use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenInterface;

final class TokenAdapter implements TokenInterface
{
    private const ROOT_KEYWORDS = [
        'OR' => true,
    ];
    private const NOT_ROOT_KEYWORDS = [
        'INTO' => true,
        'CHECK' => true,
        'ON' => true,
    ];
    private const DDL_KEYWORDS = [
        'ALTER' => true,
        'CREATE' => true,
        'DROP' => true,
        'RENAME' => true,
        'TRUNCATE' => true,
    ];
    private const LOGICAL_OPERATORS = [
        'AND' => true,
        'NOT' => true,
        'OR' => true,
        'XOR' => true,
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

        $keyword = $this->token->keyword;

        // Keyword most not be in the no-no list
        if (isset(self::NOT_ROOT_KEYWORDS[$keyword])) {
            return false;
        }

        // Must not be a JOIN
        if (isset(JoinKeyword::$JOINS[$keyword])) {
            return false;
        }

        return isset(self::ROOT_KEYWORDS[$keyword])
            || isset(Parser::$STATEMENT_PARSERS[$keyword])
            || isset(Parser::$KEYWORD_PARSERS[$keyword]);
    }

    public function isWhitespace(): bool
    {
        return $this->token->type === Token::TYPE_WHITESPACE;
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
        return Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] === 'union';
    }

    public function isKeyword(): bool
    {
        return $this->token->type === Token::TYPE_KEYWORD;
    }

    public function isNone(): bool
    {
        return $this->token->type === Token::TYPE_NONE;
    }

    public function isSingleSpace(): bool
    {
        return $this->token->token === ' ';
    }

    public function isJoin(): bool
    {
        return Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] === 'join';
    }

    public function isOn(): bool
    {
        return $this->token->keyword === 'ON';
    }

    public function hasTwoWords(): bool
    {
        return substr_count($this->token->token, ' ') === 1;
    }

    public function hasTwoLineBreaks(): bool
    {
        return substr_count($this->token->token, PHP_EOL) === 2;
    }

    public function isDdlKeyword(): bool
    {
        return isset(self::DDL_KEYWORDS[$this->token->keyword]);
    }

    public function isWhere(): bool
    {
        return Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] === 'where';
    }

    public function isLogicalOperator(): bool
    {
        return isset(self::LOGICAL_OPERATORS[$this->token->keyword]);
    }

    public function isBetween(): bool
    {
        return $this->token->keyword === 'BETWEEN';
    }

    public function toString(): string
    {
        return $this->token->token ?? '';
    }
}
