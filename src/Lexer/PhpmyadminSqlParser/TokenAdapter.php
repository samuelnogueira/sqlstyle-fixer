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
        'CHECK' => true,
        'DESC'  => true,
        'INTO'  => true,
        'ON'    => true,
    ];
    private const DDL_KEYWORDS = [
        'ALTER'    => true,
        'CREATE'   => true,
        'DROP'     => true,
        'RENAME'   => true,
        'TRUNCATE' => true,
    ];
    private const LOGICAL_OPERATORS = [
        'AND' => true,
        'NOT' => true,
        'OR'  => true,
        'XOR' => true,
    ];
    private const CASE_CLAUSES = [
        'WHEN' => true,
        'ELSE' => true,
    ];
    private const SCALAR_TYPES = [
        Token::TYPE_BOOL   => true,
        Token::TYPE_NUMBER => true,
        Token::TYPE_STRING => true,
    ];

    public function __construct(
        private readonly Token $token,
    ) {}

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
        return (Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] ?? null) === 'union';
    }

    public function isKeyword(): bool
    {
        return $this->token->type === Token::TYPE_KEYWORD;
    }

    /**
     * Whether token is invalid or its type cannot be determined because of the ambiguous context. Further analysis
     * might be required to detect its type.
     */
    public function isNone(): bool
    {
        return $this->token->type === Token::TYPE_NONE;
    }

    public function isJoin(): bool
    {
        return (Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] ?? null) === 'join';
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
        return (Parser::$KEYWORD_PARSERS[$this->token->keyword]['field'] ?? null) === 'where';
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

    public function isAlias(): bool
    {
        return $this->token->keyword === 'AS';
    }

    public function isDistinct(): bool
    {
        return $this->token->keyword === 'DISTINCT';
    }

    public function isPartitionBy(): bool
    {
        return $this->token->keyword === 'PARTITION BY';
    }

    public function isCase(): bool
    {
        return $this->token->keyword === 'CASE';
    }

    public function isCaseClause(): bool
    {
        return isset(self::CASE_CLAUSES[$this->token->keyword]);
    }

    public function isEnd(): bool
    {
        return $this->token->keyword === 'END';
    }

    public function isThen(): bool
    {
        return $this->token->keyword === 'THEN';
    }

    public function isScalar(): bool
    {
        return isset(self::SCALAR_TYPES[$this->token->type]);
    }

    public function hasLineBreak(): bool
    {
        return substr_count($this->token->token, PHP_EOL) > 0;
    }

    public function isOperator(): bool
    {
        return $this->token->type === Token::TYPE_OPERATOR
            && $this->token->token !== '.';
    }
}
