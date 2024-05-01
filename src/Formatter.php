<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use Samuelnogueira\SqlstyleFixer\Lexer\LexerInterface;
use Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser\LexerAdapter;
use Samuelnogueira\SqlstyleFixer\Lexer\StatementSplitter;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenInterface;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenListInterface;

/**
 * @api
 */
final class Formatter
{
    private readonly LexerInterface $lexer;

    /** @var list<int> */
    private array $riverStack = [];
    private int|null $logicalOperatorOffset = null;
    private int $cursorCol = 0;

    public function __construct(LexerInterface|null $lexer = null)
    {
        $this->lexer = $lexer ?? new LexerAdapter();
    }

    public function formatString(string $sql): string
    {
        $list = $this->lexer->parseString($sql);

        foreach (StatementSplitter::fromTokenList($list)->iterateNonDdlStatements() as $statementTokenList) {
            $this->formatList($statementTokenList);
        }

        return $list->toString();
    }

    private function formatList(TokenListInterface $list): void
    {
        $prevJoin = null;
        $prevKeyword = null;
        $tokens = $list->toArray();
        $this->initializeRiver($list);
        foreach ($tokens as $i => $token) {
            // Ignore whitespaces
            if ($token->isWhitespace()) {
                continue;
            }

            $this->updateCursorCol($tokens, $i);

            $prev = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;
            $prevNonWs = $prev?->isWhitespace() === false ? $prev : ($tokens[$i - 2] ?? null);
            $nextNonWs = $next?->isWhitespace() === false ? $next : ($tokens[$i + 2] ?? null);

            $this->handleCasing($token);

            // Stop at the first handler that changes something (i.e. returns true).
            $this->handleParenthesis($prevNonWs, $prev, $token, $nextNonWs)
            || $this->handleCaseStatement($prevNonWs, $prev, $token)
            || $this->handleUnion($prev, $token, $next)
            || $this->handleJoin($prevJoin, $prev, $token)
            || $this->handleLogicalOperator($prevKeyword, $prev, $token, $next)
            || $this->handleAlias($prev, $token, $next)
            || $this->handleRootKeyword($prevNonWs, $prev, $token, $next)
            || $this->handleExpression($prevNonWs, $prev, $token, $nextNonWs);

            if ($token->isJoin()) {
                $prevJoin = $token;
            }

            if ($token->isKeyword()) {
                $prevKeyword = $token;
            }
        }
    }

    private function handleCasing(TokenInterface $token): void
    {
        if (!$token->isKeyword()) {
            return;
        }

        $token->toUpperCase();
    }

    private function handleParenthesis(
        TokenInterface|null $prevNonWs,
        TokenInterface|null $prev,
        TokenInterface      $token,
        TokenInterface|null $nextNonWs,
    ): bool {
        if ($token->isOpenParenthesis()) {
            $baseRiver = $this->river();
            if ($nextNonWs !== null && self::startsNewRiver($nextNonWs) && !($prevNonWs?->isUnion() ?? false)) {
                $baseRiver = $this->cursorCol + $nextNonWs->firstWordLength() + 1;
            }

            array_unshift($this->riverStack, $baseRiver);

            return true;
        } elseif ($token->isCloseParenthesis()) {
            self::replaceWhitespace($prev, '');

            array_shift($this->riverStack);

            return true;
        } else {
            return false;
        }
    }

    private function handleUnion(TokenInterface|null $prev, TokenInterface $token, TokenInterface|null $next): bool
    {
        if (!$token->isUnion()) {
            return false;
        }

        if ($prev?->isWhitespace() === true) {
            $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
            $prev->replaceContent(PHP_EOL . PHP_EOL . $leftPadding);
        }

        self::replaceWhitespace($next, PHP_EOL . PHP_EOL);

        return true;
    }

    private function handleLogicalOperator(
        TokenInterface|null $prevKeyword,
        TokenInterface|null $prev,
        TokenInterface $token,
        TokenInterface|null $next,
    ): bool {
        if (!$token->isLogicalOperator()) {
            return false;
        }

        if ($prev?->isWhitespace() === true) {
            if ($this->logicalOperatorOffset !== null) {
                $prev->replaceContent(PHP_EOL . str_repeat(' ', $this->river() + $this->logicalOperatorOffset));
            } elseif ($prevKeyword?->isBetween() === true) {
                $prev->replaceContent(' ');
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        self::replaceWhitespace($next, ' ');

        return true;
    }

    private function handleRootKeyword(
        TokenInterface|null $prevNonWs,
        TokenInterface|null $prev,
        TokenInterface      $token,
        TokenInterface|null $next,
    ): bool {
        if (!$token->isRootKeyword()) {
            return false;
        }

        if ($prev?->isWhitespace() === true) {
            if ($prevNonWs?->isOpenParenthesis() === true) {
                $prev->replaceContent('');
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        self::replaceWhitespace($next, ' ');

        return true;
    }

    private function handleExpression(
        TokenInterface|null $prevNonWs,
        TokenInterface|null $prev,
        TokenInterface $token,
        TokenInterface|null $nextNonWs,
    ): bool {
        if (
            !$token->isScalar() &&
            !$token->isNone() &&
            !self::isFunction($token, $nextNonWs)
        ) {
            return false;
        }

        $this->alignExpression($prevNonWs, $prev);

        return true;
    }

    private function handleJoin(TokenInterface|null $prevJoin, TokenInterface|null $prev, TokenInterface $token): bool
    {
        if (!$token->isJoin() && !$token->isOn()) {
            if ($token->isWhere()) {
                $this->logicalOperatorOffset = null;
            }

            return false;
        }

        $this->logicalOperatorOffset = 4;

        if ($prev?->isWhitespace() === true) {
            if (
                $token->hasTwoWords() ||
                (
                    $token->isOn() &&
                    ($prevJoin?->hasTwoWords() ?? false)
                )
            ) {
                $this->alignOtherSideOfRiverKeepLineBreak($prev);
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        return true;
    }

    private function handleAlias(TokenInterface|null $prev, TokenInterface $token, TokenInterface|null $next): bool
    {
        if (!$token->isAlias()) {
            return false;
        }

        self::replaceWhitespace($prev, ' ');
        self::replaceWhitespace($next, ' ');

        return true;
    }

    private function handleCaseStatement(
        TokenInterface|null $prevNonWs,
        TokenInterface|null $prev,
        TokenInterface $token,
    ): bool {
        if (
            ! $token->isCase() &&
            ! $token->isCaseClause() &&
            ! $token->isThen() &&
            ! $token->isEnd()
        ) {
            return false;
        }

        if ($token->isCase()) {
            assert($this->logicalOperatorOffset === null);
            $this->logicalOperatorOffset = 6;
        }

        if ($token->isEnd()) {
            assert($this->logicalOperatorOffset === 6);
            $this->logicalOperatorOffset = null;
        }

        if ($token->isThen()) {
            self::replaceWhitespace($prev, ' ');
        } else {
            $this->alignExpression($prevNonWs, $prev);
        }

        return true;
    }

    private function initializeRiver(TokenListInterface $list): void
    {
        $this->riverStack = $list->firstNonWhitespace()?->isOpenParenthesis() === true ? [7] : [6];
    }

    private function river(): int
    {
        assert($this->riverStack !== []);

        return $this->riverStack[0];
    }

    private function alignCharacterBoundary(TokenInterface $token, TokenInterface $prev): void
    {
        $river = $this->river();
        $firstWordLength = $token->firstWordLength();

        assert($prev->isWhitespace());
        assert($river >= $firstWordLength);

        $prev->replaceContent(PHP_EOL . str_repeat(' ', $river - $firstWordLength));
    }

    private function alignOtherSideOfRiver(TokenInterface|null $prev): void
    {
        self::replaceWhitespace($prev, ' ');
        $this->alignOtherSideOfRiverKeepLineBreak($prev);
    }

    private function alignOtherSideOfRiverKeepLineBreak(TokenInterface|null $prev): void
    {
        $lineBreak = PHP_EOL;
        if ($prev?->hasTwoLineBreaks() === true) {
            $lineBreak .= PHP_EOL;
        }

        self::replaceWhitespace($prev, $lineBreak . str_repeat(' ', $this->river() + 1));
    }

    private function alignExpression(TokenInterface|null $prevNonWs, TokenInterface|null $prev): void
    {
        if ($prevNonWs?->isOpenParenthesis() === true) {
            self::replaceWhitespace($prev, '');
        } elseif (
            // First expression should be in the same line as the root keyword
            $prevNonWs?->isRootKeyword() === true ||
            $prevNonWs?->isDistinct() === true ||
            $prev?->isOperator() === true
        ) {
            self::replaceWhitespace($prev, ' ');
        } elseif ($prev?->hasLineBreak() === true) {
            $this->alignOtherSideOfRiver($prev);
        }
    }

    /**
     * @param list<TokenInterface> $tokens
     */
    private function updateCursorCol(array $tokens, int $index): void
    {
        $this->cursorCol = 0;
        for ($i = $index - 1; $i >= 0; $i--) {
            $string = $tokens[$i]->toString();
            $nlPos  = strrpos($string, PHP_EOL);

            $this->cursorCol += strlen($string);
            if ($nlPos !== false) {
                // Add + 1 to account for the PHP_EOL in the string
                $this->cursorCol -= $nlPos + 1;

                return;
            }
        }
    }

    /**
     * Returns TRUE if token should start a new river (ex. sub-query).
     */
    private static function startsNewRiver(TokenInterface $token): bool
    {
        return $token->isSelect() === true
            || $token->isPartitionBy() === true;
    }

    private static function replaceWhitespace(TokenInterface|null $token, string $content): void
    {
        // Ugly hack to insert spaces when we're missing a whitespace token.
        $token?->replaceContent(trim($token->toString()) . $content);
    }

    private static function isFunction(TokenInterface $token, TokenInterface|null $nextNonWs): bool
    {
        return $token->isKeyword() && ($nextNonWs?->isOpenParenthesis() === true);
    }
}
