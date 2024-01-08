<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser;

use Iterator;
use PhpMyAdmin\SqlParser\TokensList;
use Samuelnogueira\SqlstyleFixer\Parser\TokenListInterface;

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

    public function iterateNonWhitespaceTokens(): iterable
    {
        foreach ($this->iterate() as $token) {
            if ($token->isWhitespace()) {
                continue;
            }

            yield $token;
        }
    }

    public function copySlice(int $offset): TokenListInterface
    {
        return new self(new TokensList(array_slice($this->list->tokens, $offset)));
    }
}
