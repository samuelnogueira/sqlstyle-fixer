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
        private readonly TokensList $list,
    ) {}

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
}
