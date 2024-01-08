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
        if ($tokens === []) {
            return;
        }

        $riverStack = [self::getRiver($list)];
        foreach ($tokens as $i => $token) {
            // Ignore whitespaces
            if ($token->isWhitespace()) {
                continue;
            }

            $prev = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;

            if ($token->isOpenParenthesis()) {
                $nextNonWs = $next?->isWhitespace() === false ? $next : ($tokens[$i + 2] ?? null);
                $prevNonWs = $prev?->isWhitespace() === false ? $prev : ($tokens[$i - 2] ?? null);

                $nextRiver = $riverStack[0];
                if ($nextNonWs->isSelect() && ! ($prevNonWs?->isUnion() ?? false)) {
                    $nextRiver = $riverStack[0] + 1 + self::getRiver($list->copySlice($i));
                }

                array_unshift($riverStack, $nextRiver);
            } elseif ($token->isCloseParenthesis()) {
                array_shift($riverStack);
            }

            $river = $riverStack[0];

            $this->handleCasing($token);

            // Stop at the first handler that changes something (i.e. returns true).
            $this->handleUnion($token, $prev, $next, $river) ||
            $this->handleRootKeyword($token, $prev, $next, $river);
        }
    }

    private function handleUnion(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next, int $river): bool
    {
        if (! $token->isUnion()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $previous->replaceContent(PHP_EOL . PHP_EOL . str_repeat(' ', $river - $token->firstWordLength()));
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(PHP_EOL . PHP_EOL);
        }

        return true;
    }

    private function handleRootKeyword(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next, int $river): bool
    {
        if (! $token->isRootKeyword()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $previous->replaceContent(PHP_EOL . str_repeat(' ', $river - $token->firstWordLength()));
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
        foreach ($list->iterateNonWhitespaceTokens() as $token) {
            if ($token->isOpenParenthesis()) {
                $river++;
            } elseif ($token->isRootKeyword()) {
                return $river + $token->firstWordLength();
            }
        }

        throw new LogicException('Could not determine river');
    }
}
