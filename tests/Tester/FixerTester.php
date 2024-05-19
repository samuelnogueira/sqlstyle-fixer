<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixerTests\Tester;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class FixerTester
{
    public function __construct(private readonly FixerInterface $fixer) {}

    public function fixCode(string $code): string
    {
        $tokens = Tokens::fromCode($code);

        if (! $this->fixer->isCandidate($tokens)) {
            return $code;
        }

        $this->fixer->fix(new class ('') extends SplFileInfo {}, $tokens);

        return $tokens->generateCode();
    }
}
