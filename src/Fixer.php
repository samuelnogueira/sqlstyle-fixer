<?php /** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

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
        $tokens = $list->tokens();
        if ($tokens === []) {
            return;
        }

        $firstRootKeyword = $list->firstRootKeyword();
        $mainRiver = $firstRootKeyword?->firstWordLength() ?? 0;
        $subQueryLayer = 0;
        foreach ($tokens as $i => $token) {
            $previous = $tokens[$i - 1] ?? null;
            $next     = $tokens[$i + 1] ?? null;

            if ($token->isOpenParenthesis()) {
                $mainRiver++;
            }

            if ($token->isCloseParenthesis()) {
                $subQueryLayer = $subQueryLayer > 0 ? $subQueryLayer - 1 : 0;
                $mainRiver--;
            }

            if ($token->isSelect() && $token !== $firstRootKeyword) {
                $subQueryLayer++;
                $mainRiver += $token->firstWordLength();
            }

            $riverOffset = $mainRiver + ($subQueryLayer * 7);

            $this->handleCasing($token);

            // Stop at the first handler that changes something (i.e. returns true).
            $this->handleUnion($token, $previous, $next, $riverOffset) ||
            $this->handleRootKeyword($token, $previous, $next, $riverOffset);
        }
    }

    private function handleUnion(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next, int $riverOffset): bool
    {
        if (! $token->isUnion()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $previous->replaceContent(PHP_EOL . PHP_EOL . str_repeat(' ', $riverOffset - $token->firstWordLength()));
        }

        if ($next !== null && $next->isWhitespace()) {
            $next->replaceContent(PHP_EOL . PHP_EOL);
        }

        return true;
    }

    private function handleRootKeyword(TokenInterface $token, TokenInterface|null $previous, TokenInterface|null $next, int $riverOffset): bool
    {
        if (! $token->isRootKeyword()) {
            return false;
        }

        if ($previous !== null && $previous->isWhitespace()) {
            $previous->replaceContent(PHP_EOL . str_repeat(' ', $riverOffset - $token->firstWordLength()));
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
}
