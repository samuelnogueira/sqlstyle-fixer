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
 * @immutable
 */
final class Fixer
{
    private readonly LexerInterface $lexer;

    /** @var list<int> */
    private array $riverStack = [];
    private bool $insideJoin = false;
    private int $cursorCol = 0;
    public string|null $debugString = null;

    public function __construct(LexerInterface|null $lexer = null, private readonly bool $debug = false)
    {
        $this->lexer = $lexer ?? new LexerAdapter();
    }

    public function fixString(string $sql): string
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
            if ($this->debug) {
                $this->updateDebugString($tokens, $i);
            }

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
            || $this->handleUnion($prev, $token, $next)
            || $this->handleJoin($prevJoin, $prev, $token)
            || $this->handleLogicalOperator($prevKeyword, $prev, $token, $next)
            || $this->handleAlias($prev, $token, $next)
            || $this->handleRootKeyword($prevNonWs, $prev, $token, $next)
            || $this->handleExpression($prevNonWs, $prev, $token);

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
            if ($nextNonWs?->isSelect() === true && !($prevNonWs?->isUnion() ?? false)) {
                $baseRiver = $this->cursorCol + $nextNonWs->firstWordLength() + 1;
            }

            array_unshift($this->riverStack, $baseRiver);

            return true;
        } elseif ($token->isCloseParenthesis()) {
            if ($prev?->isWhitespace() ?? false) {
                $prev->replaceContent('');
            }

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

        if ($prev !== null && $prev->isWhitespace()) {
            $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
            $prev->replaceContent(PHP_EOL . PHP_EOL . $leftPadding);
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(PHP_EOL . PHP_EOL);
        }

        return true;
    }

    private function handleLogicalOperator(
        TokenInterface|null $prevKeyword,
        TokenInterface|null $prev,
        TokenInterface $token,
        TokenInterface|null $next
    ): bool {
        if (!$token->isLogicalOperator()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            if ($this->insideJoin) {
                $prev->replaceContent(PHP_EOL . str_repeat(' ', $this->river() + 4));
            } elseif ($prevKeyword?->isBetween() ?? false) {
                $prev->replaceContent(' ');
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(' ');
        }

        return true;
    }

    private function handleRootKeyword(
        TokenInterface|null $prevNonWs,
        TokenInterface|null $prev,
        TokenInterface      $token,
        TokenInterface|null $next
    ): bool {
        if (!$token->isRootKeyword()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            if ($prevNonWs?->isOpenParenthesis() ?? false) {
                $prev->replaceContent('');
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(' ');
        }

        return true;
    }

    private function handleExpression(TokenInterface|null $prevNonWs, TokenInterface|null $prev, TokenInterface $token): bool
    {
        if (!$token->isNone()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            if ($prevNonWs !== null && ($prevNonWs->isRootKeyword() || $prevNonWs->isDistinct())) {
                // First expression should be in the same line as the root keyword
                $prev->replaceContent(' ');
            } elseif (! $prev->isSingleSpace()) {
                // Only replace previous whitespace content if it's not an accepted format already
                $this->alignOtherSideOfRiver($prev);
            }
        }

        return true;
    }

    private function handleJoin(TokenInterface|null $prevJoin, TokenInterface|null $prev, TokenInterface $token): bool
    {
        if (!$token->isJoin() && !$token->isOn()) {
            if ($token->isWhere()) {
                $this->insideJoin = false;
            }

            return false;
        }

        $this->insideJoin = true;

        if ($prev !== null && $prev->isWhitespace()) {
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

        if ($prev !== null && $prev->isWhitespace()) {
            $prev->replaceContent(' ');
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(' ');
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

    private function alignOtherSideOfRiver(TokenInterface $prev): void
    {
        $prev->replaceContent(' ');
        $this->alignOtherSideOfRiverKeepLineBreak($prev);
    }

    private function alignOtherSideOfRiverKeepLineBreak(TokenInterface $prev): void
    {
        $lineBreak = PHP_EOL;
        if ($prev->hasTwoLineBreaks()) {
            $lineBreak .= PHP_EOL;
        }

        $prev->replaceContent($lineBreak . str_repeat(' ', $this->river() + 1));
    }

    /**
     * @param list<TokenInterface> $tokens
     */
    private function updateDebugString(array $tokens, int $index): void
    {
        $this->debugString = '';
        foreach ($tokens as $i => $token) {
            $this->debugString .= $i === $index
                ? "\e" . $token->toString() . "\e"
                : $token->toString();
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
}
