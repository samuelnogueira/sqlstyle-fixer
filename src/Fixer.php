<?php /** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use LogicException;
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

    public function __construct(private LexerInterface|null $lexer = null)
    {
        $this->lexer = $this->lexer ?? new LexerAdapter();
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
        $this->riverStack = [self::getRiver($list)];
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
            $this->handleParenthesis($token, $prevNonWs, $nextNonWs) ||
            $this->handleUnion($token, $prev, $next) ||
            $this->handleRootKeyword($token, $prev, $next);
        }
    }

    private function handleParenthesis(
        TokenInterface $token,
        TokenInterface|null $prevNonWs,
        TokenInterface|null $nextNonWs,
    ): bool {
        if ($token->isOpenParenthesis()) {
            $baseRiver = $prevNonWs !== null ? $this->river() : -1;
            if ($nextNonWs?->isSelect() === true && ! ($prevNonWs?->isUnion() ?? false)) {
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

    private function handleUnion(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next): bool
    {
        if (! $token->isUnion()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
            $previous->replaceContent(PHP_EOL . PHP_EOL . $leftPadding);
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(PHP_EOL . PHP_EOL);
        }

        return true;
    }

    private function handleRootKeyword(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next): bool
    {
        if (! $token->isRootKeyword()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $leftPadding = str_repeat(' ', $this->river() - $token->firstWordLength());
            $previous->replaceContent(PHP_EOL . $leftPadding);
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(' ');
        }

        return true;
    }

    private function handleCasing(TokenInterface $token): void
    {
        if (! $token->isKeyword()) {
            return;
        }

        $token->toUpperCase();
    }

    private static function getRiver(TokenListInterface $list): int
    {
        $river = 0;
        foreach ($list->iterate() as $token) {
            if ($token->isOpenParenthesis()) {
                $river++;
            } elseif ($token->isRootKeyword()) {
                return $river + $token->firstWordLength();
            }
        }

        throw new LogicException('Could not determine river');
    }

    private function river(): int
    {
        return $this->riverStack[0] ?? 0;
    }
}
