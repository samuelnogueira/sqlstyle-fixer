<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use LogicException;
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
    /** @var list<int> */
    private array $riverStack = [];
    private bool $insideJoin = false;
    private readonly LexerInterface $lexer;

    public function __construct(LexerInterface|null $lexer = null)
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
            // Ignore whitespaces
            if ($token->isWhitespace()) {
                continue;
            }

            $prev = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;
            $prevNonWs = $prev?->isWhitespace() === false ? $prev : ($tokens[$i - 2] ?? null);
            $nextNonWs = $next?->isWhitespace() === false ? $next : ($tokens[$i + 2] ?? null);

            $this->handleCasing($token);

            // Stop at the first handler that changes something (i.e. returns true).
            $this->handleParenthesis($prevNonWs, $token, $nextNonWs)
            || $this->handleUnion($prev, $token, $next)
            || $this->handleJoin($prevJoin, $prev, $token)
            || $this->handleLogicalOperator($prevKeyword, $prev, $token, $next)
            || $this->handleRootKeyword($prev, $token, $next)
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
        TokenInterface      $token,
        TokenInterface|null $nextNonWs,
    ): bool {
        if ($token->isOpenParenthesis()) {
            $baseRiver = $prevNonWs !== null ? $this->river() : -1;
            if ($nextNonWs?->isSelect() === true && !($prevNonWs?->isUnion() ?? false)) {
                $baseRiver += 8;
            }

            array_unshift($this->riverStack, $baseRiver);

            return true;
        } elseif ($token->isCloseParenthesis()) {
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
            } elseif ($prevKeyword->isBetween()) {
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

    private function handleRootKeyword(TokenInterface|null $prev, TokenInterface $token, TokenInterface|null $next): bool
    {
        if (!$token->isRootKeyword()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            $this->alignCharacterBoundary($token, $prev);
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
            if ($prevNonWs?->isRootKeyword() === true) {
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
            if ($token->hasTwoWords() || ($token->isOn() && $prevJoin->hasTwoWords())) {
                $this->alignOtherSideOfRiverKeepLineBreak($prev);
            } else {
                $this->alignCharacterBoundary($token, $prev);
            }
        }

        return true;
    }

    private function initializeRiver(TokenListInterface $list): void
    {
        $river = 0;
        foreach ($list->iterate() as $token) {
            if ($token->isOpenParenthesis()) {
                $river++;
            } elseif ($token->isRootKeyword()) {
                $this->riverStack = [$river + $token->firstWordLength()];

                return;
            }
        }

        throw new LogicException('Could not determine river');
    }

    private function river(): int
    {
        return $this->riverStack[0] ?? 0;
    }

    private function alignCharacterBoundary(TokenInterface $token, TokenInterface $prev): void
    {
        assert($prev->isWhitespace());
        $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
        $prev->replaceContent(PHP_EOL . $leftPadding);
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
}
