<?php
declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Lexer;

use Iterator;

final class StatementSplitter
{
    public function __construct(
        private readonly TokenListInterface $tokenList,
    ) {
    }

    public static function fromTokenList(TokenListInterface $tokenList): self
    {
        return new self($tokenList);
    }

    /**
     * @return Iterator<TokenListInterface>
     */
    public function iterateNonDdlStatements(): Iterator
    {
        foreach ($this->tokenList->iterate() as $token) {
            /** @var TokenInterface $token */
            if ($token->isDdlKeyword()) {
                return;
            }
        }

        yield $this->tokenList;
    }
}
