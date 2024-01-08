<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser;

use Iterator;

interface TokenListInterface
{
    /**
     * @return Iterator<TokenInterface>
     */
    public function iterate(): Iterator;

    /**
     * @return list<TokenInterface>
     */
    public function toArray(): array;

    public function toString(): string;

    public function copySlice(int $offset): self;
}
