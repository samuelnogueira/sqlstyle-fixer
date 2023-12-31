<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use LogicException;
use PhpMyAdmin\SqlParser\Parser;
use Samuelnogueira\SqlstyleFixer\Parser\LexerInterface;
use Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser\LexerAdapter;
use Samuelnogueira\SqlstyleFixer\Parser\TokenInterface;
use Samuelnogueira\SqlstyleFixer\Parser\TokenListInterface;

/**
 * @api
 * @immutable
 */
final class Fixer
{
    /** @var list<int> */
    private array $riverStack = [];
    private readonly LexerInterface $lexer;

    public function __construct(LexerInterface|null $lexer = null)
    {
        $this->lexer = $lexer ?? new LexerAdapter();
    }

    public function fixString(string $sql): string
    {
        $list = $this->lexer->parseString($sql);

        $this->formatList($list);

        return $list->toString();
    }

    private function formatList(TokenListInterface $list): void
    {
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
            $this->handleParenthesis($token, $prevNonWs, $nextNonWs)
            || $this->handleUnion($token, $prev, $next)
            || $this->handleJoin($token, $prev, $next)
            || $this->handleRootKeyword($token, $prev, $next)
            || $this->handleExpression($token, $prev, $prevNonWs);
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
        TokenInterface      $token,
        TokenInterface|null $prevNonWs,
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

    private function handleUnion(TokenInterface $token, TokenInterface|null $prev, TokenInterface|null $next): bool
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

    private function handleRootKeyword(TokenInterface $token, TokenInterface|null $prev, TokenInterface|null $next): bool
    {
        if (!$token->isRootKeyword()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
            $prev->replaceContent(PHP_EOL . $leftPadding);
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(' ');
        }

        return true;
    }

    private function handleExpression(TokenInterface $token, TokenInterface|null $prev, TokenInterface|null $prevNonWs): bool
    {
        if (!$token->isNone()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            // Only replace previous whitespace content if it's not an accepted format already
            if ($prevNonWs?->isKeyword() === true) {
                $prev->replaceContent(' ');
            } elseif (! $prev->isSingleSpace()) {
                $prev->replaceContent(PHP_EOL . str_repeat(' ', $this->river() + 1));
            }
        }

        return true;
    }

    private function handleJoin(TokenInterface $token, TokenInterface|null $prev, TokenInterface|null $next): bool
    {
        if (!$token->isJoin()) {
            return false;
        }

        if ($prev !== null && $prev->isWhitespace()) {
            $prev->replaceContent(PHP_EOL . str_repeat(' ', $this->river() + 1));
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
}
