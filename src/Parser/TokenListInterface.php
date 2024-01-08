<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser;

interface TokenListInterface
{
    /**
     * @return list<TokenInterface>
     */
    public function tokens(): array;

    public function toString(): string;

    public function remove(int $index);

    public function firstRootKeyword(): TokenInterface|null;
}
