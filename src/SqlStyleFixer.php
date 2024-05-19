<?php

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class SqlStyleFixer implements FixerInterface
{
    private const CANDIDATE_TOKENS = [
        T_START_HEREDOC,
        T_END_HEREDOC,
    ];

    private const HEREDOC_IDENTIFIERS = [
        '<<<SQL',
        '<<<DQL',
        '<<<\'SQL\'',
        '<<<\'DQL\'',
    ];

    private readonly FormatterInterface $formatter;

    public function __construct(FormatterInterface|null $formatter = null)
    {
        $this->formatter = $formatter ?? new Formatter();
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(self::CANDIDATE_TOKENS);
    }

    public function isRisky(): bool
    {
        // We are changing SQL/DQL queries which is inherently risky.
        return true;
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if ($token?->isGivenKind(T_START_HEREDOC) !== true) {
                continue;
            }

            if (! self::isHeredocIdentifierAllowed($token)) {
                continue;
            }

            $stringTokenIndex = $tokens->getNextMeaningfulToken($index);
            if ($stringTokenIndex === null) {
                continue;
            }

            $stringToken = $tokens[$stringTokenIndex] ?? null;
            if ($stringToken?->isGivenKind(T_ENCAPSED_AND_WHITESPACE) !== true) {
                continue;
            }

            $tokens[$stringTokenIndex] = new Token(
                [T_ENCAPSED_AND_WHITESPACE, $this->formatter->formatString($stringToken->getContent())],
            );
        }
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition('Formats SQL and DQL queries to a specific style', []);
    }

    public function getName(): string
    {
        return 'sql_style';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    private static function isHeredocIdentifierAllowed(Token $token): bool
    {
        $content = $token->getContent();

        foreach (self::HEREDOC_IDENTIFIERS as $heredocIdentifier) {
            if (str_starts_with(strtoupper($content), $heredocIdentifier)) {
                return true;
            }
        }

        return false;
    }
}
