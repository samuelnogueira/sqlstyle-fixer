<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Lexer\PhpmyadminSqlParser;

use Iterator;
use PhpMyAdmin\SqlParser\TokensList;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenInterface;
use Samuelnogueira\SqlstyleFixer\Lexer\TokenListInterface;

final class TokenListAdapter implements TokenListInterface
{
    public function __construct(
        private readonly TokensList $list
    ) {
    }

    /**
     * @inheritDoc
     */
    public function iterate(): Iterator
    {
        foreach ($this->list->tokens as $token) {
            yield new TokenAdapter($token);
        }
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return iterator_to_array($this->iterate(), false);
    }

    public function toString(): string
    {
        return TokensList::build($this->list);
    }

    public function copySlice(int $offset): TokenListInterface
    {
        return new self(new TokensList(array_slice($this->list->tokens, $offset)));
    }

    public function firstNonWhitespace(): TokenInterface|null
    {
        foreach ($this->iterate() as $token) {
            if ($token->isWhitespace()) {
                continue;
            }

            return $token;
        }

        return null;
    }

    public function isFirstNonWhitespace(int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$this->at($i)->isWhitespace()) {
                return false;
            }
        }

        return true;
    }

    private function at(int $index): TokenInterface
    {
        return new TokenAdapter($this->list->tokens[$index]);
    }
}
