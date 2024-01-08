<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Parser;

interface TokenInterface
{
    public function isRootKeyword(): bool;

    public function isWhitespace(): bool;

    public function isOpenParenthesis(): bool;

    public function isCloseParenthesis(): bool;

    public function isSelect(): bool;

    public function isUnion(): bool;

    public function isKeyword(): bool;

    public function toString(): string;

    public function firstWordLength(): int;

    public function toUpperCase(): void;

    public function replaceContent(string $content): void;
}
