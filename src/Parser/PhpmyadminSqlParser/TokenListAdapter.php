<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser\PhpmyadminSqlParser;

use OutOfRangeException;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;
use Samuelnogueira\SqlstyleFixer\Parser\TokenInterface;
use Samuelnogueira\SqlstyleFixer\Parser\TokenListInterface;

final class TokenListAdapter implements TokenListInterface
{
    /** @var list<TokenAdapter> */
    private array $tokens;

    public function __construct(TokensList $list)
    {
        $this->tokens = array_map(
            static fn (Token $token) => new TokenAdapter($token),
            $list->tokens,
        );
    }

    public function tokens(): array
    {
        return $this->tokens;
    }

    public function toString(): string
    {
        $string = '';
        foreach ($this->tokens as $token) {
            $string .= $token->toString();
        }

        return $string;
    }

    public function remove(int $index): void
    {
        if (! isset($this->tokens[$index])) {
            throw new OutOfRangeException(sprintf('Invalid index %d', $index));
        }

        unset($this->tokens[$index]);

        $this->tokens = array_values($this->tokens);
    }

    public function firstRootKeyword(): TokenInterface|null
    {
        foreach ($this->tokens as $token) {
            if ($token->isRootKeyword()) {
                return $token;
            }
        }

        return null;
    }
}
