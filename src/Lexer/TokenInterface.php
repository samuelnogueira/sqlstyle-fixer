<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer\Lexer;

interface TokenInterface
{
    public function isRootKeyword(): bool;

    public function isWhitespace(): bool;

    public function isOpenParenthesis(): bool;

    public function isCloseParenthesis(): bool;

    public function isSelect(): bool;

    public function isPartitionBy(): bool;

    public function isUnion(): bool;

    public function isJoin(): bool;

    public function isOn(): bool;

    public function isKeyword(): bool;

    public function isNone(): bool;

    public function isWhere(): bool;

    public function isDistinct(): bool;

    public function isBetween(): bool;

    public function isLogicalOperator(): bool;

    public function hasTwoWords(): bool;

    public function isDdlKeyword(): bool;

    public function isAlias(): bool;

    public function isCase(): bool;

    public function isCaseClause(): bool;

    public function isThen(): bool;

    public function isEnd(): bool;

    public function isScalar(): bool;

    public function isOperator(): bool;

    public function hasLineBreak(): bool;

    public function hasTwoLineBreaks(): bool;

    public function firstWordLength(): int;

    public function toUpperCase(): void;

    public function replaceContent(string $content): void;

    public function toString(): string;

}
