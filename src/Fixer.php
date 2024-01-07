<?php /** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Samuelnogueira\SqlstyleFixer;

use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;

/**
 * @api
 * @immutable
 */
final class Fixer
{
    public function fixString(string $sql): string
    {
        return $this->formatList((new Lexer($sql))->list);
    }

    /**
     * Clauses that are usually short.
     *
     * These clauses share the line with the next clause.
     *
     * E.g. if INSERT was not here, the formatter would produce:
     *
     *      INSERT
     *      INTO foo
     *      VALUES(0, 0, 0),(1, 1, 1);
     *
     * Instead of:
     *
     *      INSERT INTO foo
     *      VALUES(0, 0, 0),(1, 1, 1)
     */
    private const SHORT_CLAUSES = [
        'CREATE' => true,
        'INSERT' => true,
    ];

    /**
     * Clauses that must be inlined.
     *
     * These clauses usually are short, and it's nicer to have them inline.
     */
    private const INLINE_CLAUSES = [
        'CREATE'          => true,
        'INTO'            => true,
        'LIMIT'           => true,
        'PARTITION BY'    => true,
        'PARTITION'       => true,
        'PROCEDURE'       => true,
        'SUBPARTITION BY' => true,
        'VALUES'          => true,
    ];

    /**
     * @return list<array{type: int, flags: int, function: string}>
     */
    private static function getDefaultFormats(): array
    {
        return [
            [
                'type' => Token::TYPE_KEYWORD,
                'flags' => Token::FLAG_KEYWORD_RESERVED,
                'function' => 'strtoupper',
            ],
            [
                'type' => Token::TYPE_KEYWORD,
                'flags' => 0,
                'function' => 'strtoupper',
            ],
            [
                'type' => Token::TYPE_BOOL,
                'flags' => 0,
                'function' => 'strtoupper',
            ],
            [
                'type' => Token::TYPE_NUMBER,
                'flags' => 0,
                'function' => 'strtolower',
            ],
        ];
    }

    /**
     * Formats the given list of tokens.
     *
     * @param TokensList $list the list of tokens
     *
     * @return string
     */
    private function formatList(TokensList $list): string
    {
        /**
         * The query to be returned.
         */
        $ret = '';

        /**
         * The indentation level.
         */
        $indent = 0;

        /**
         * Whether the line ended.
         */
        $lineEnded = false;

        /**
         * Whether current group is short (no linebreaks).
         */
        $shortGroup = false;

        /**
         * The name of the last clause.
         */
        $lastClause = '';

        /**
         * A stack that keeps track of the indentation level every time a new
         * block is found.
         */
        $blocksIndentation = [];

        /**
         * A stack that keeps track of the line endings every time a new block
         * is found.
         */
        $blocksLineEndings = [];

        /**
         * Previously parsed token.
         */
        $prev = null;

        for ($list->idx = 0; $list->idx < $list->count; $list->idx++) {
            $curr = $list->tokens[$list->idx];
            if ($curr->type === Token::TYPE_WHITESPACE) {
                // Whitespaces are skipped because the formatter adds its own.
                continue;
            }

            // Checking if pointers were initialized.
            if ($prev !== null) {
                // Checking if a new clause started.
                if (self::isClause($prev) !== false) {
                    $lastClause = $prev->value;
                    $ret = rtrim($ret) . PHP_EOL;
                }

                // Checking if this clause ended.
                $isClause = self::isClause($curr);

                if ($isClause !== false) {
                    if (
                        empty(self::SHORT_CLAUSES[$lastClause])
                    ) {
                        $lineEnded = true;
                        if ($indent > 0) {
                            --$indent;
                        }
                    }
                }

                // Inline JOINs
                if (
                    ($prev->type === Token::TYPE_KEYWORD && isset(JoinKeyword::$JOINS[$prev->value]))
                    || (in_array($curr->value, ['ON', 'USING'], true)
                        && isset(JoinKeyword::$JOINS[$list->tokens[$list->idx - 2]->value]))
                    || isset($list->tokens[$list->idx - 4], JoinKeyword::$JOINS[$list->tokens[$list->idx - 4]->value])
                    || isset($list->tokens[$list->idx - 6], JoinKeyword::$JOINS[$list->tokens[$list->idx - 6]->value])
                ) {
                    $lineEnded = false;
                }

                // Indenting BEGIN ... END blocks.
                if ($prev->type === Token::TYPE_KEYWORD && $prev->keyword === 'BEGIN') {
                    $lineEnded = true;
                    $blocksIndentation[] = $indent;
                    ++$indent;
                } elseif ($curr->type === Token::TYPE_KEYWORD && $curr->keyword === 'END') {
                    $lineEnded = true;
                    $indent = array_pop($blocksIndentation);
                }

                // Formatting fragments delimited by comma.
                if ($prev->type === Token::TYPE_OPERATOR && $prev->value === ',') {
                    // Fragments delimited by a comma are broken into multiple
                    // pieces only if the clause is not inlined or this fragment
                    // is between brackets that are on new line.
                    if (
                        end($blocksLineEndings) === true
                        || (
                            empty(self::INLINE_CLAUSES[$lastClause])
                            && ! $shortGroup
                        )
                    ) {
                        $lineEnded = true;
                    }
                }

                // Handling brackets.
                // Brackets are indented only if the length of the fragment between
                // them is longer than 30 characters.
                if ($prev->type === Token::TYPE_OPERATOR && $prev->value === '(') {
                    $blocksIndentation[] = $indent;
                    $shortGroup = true;
                    if (self::getGroupLength($list) > 30) {
                        ++$indent;
                        $lineEnded = true;
                        $shortGroup = false;
                    }

                    $blocksLineEndings[] = $lineEnded;
                } elseif ($curr->type === Token::TYPE_OPERATOR && $curr->value === ')') {
                    $indent = array_pop($blocksIndentation);
                    $lineEnded |= array_pop($blocksLineEndings);
                    $shortGroup = false;
                }

                // Adding the token.
                $ret .= sprintf(
                    self::isClause($prev) ? '%+6s' : '%s',
                    $this->toString($prev)
                );

                // If the line ended there is no point in adding whitespaces.
                // Also, some tokens do not have spaces before or after them.
                if (
                    // A space after delimiters that are longer than 2 characters.
                    $prev->keyword === 'DELIMITER'
                    || ! (
                        ($prev->type === Token::TYPE_OPERATOR && ($prev->value === '.' || $prev->value === '('))
                        // No space after . (
                        || ($curr->type === Token::TYPE_OPERATOR
                            && ($curr->value === '.' || $curr->value === ','
                                || $curr->value === '(' || $curr->value === ')'))
                        // No space before . , ( )
                        || $curr->type === Token::TYPE_DELIMITER && mb_strlen((string) $curr->value, 'UTF-8') < 2
                    )
                ) {
                    $ret .= ' ';
                }
            }

            // Iteration finished, consider current token as previous.
            $prev = $curr;
        }

        return trim($ret);
    }

    /**
     * Tries to print the query and returns the result.
     *
     * @param Token $token the token to be printed
     *
     * @return string
     */
    private function toString(Token $token): string
    {
        $text = $token->token;

        foreach (self::getDefaultFormats() as $format) {
            if ($token->type !== $format['type'] || ! (($token->flags & $format['flags']) === $format['flags'])) {
                continue;
            }

            // Running transformation function.
            if (! empty($format['function'])) {
                $func = $format['function'];
                $text = $func($text);
            }

            break;
        }

        return $text;
    }

    /**
     * Computes the length of a group.
     *
     * A group is delimited by a pair of brackets.
     *
     * @param TokensList $list the list of tokens
     */
    private static function getGroupLength(TokensList $list): int
    {
        /**
         * The number of opening brackets found.
         * This counter starts at one because by the time this function called,
         * the list already advanced one position and the opening bracket was
         * already parsed.
         */
        $count = 1;

        /**
         * The length of this group.
         */
        $length = 0;

        for ($idx = $list->idx; $idx < $list->count; ++$idx) {
            // Counting the brackets.
            if ($list->tokens[$idx]->type === Token::TYPE_OPERATOR) {
                if ($list->tokens[$idx]->value === '(') {
                    ++$count;
                } elseif ($list->tokens[$idx]->value === ')') {
                    --$count;
                    if ($count === 0) {
                        break;
                    }
                }
            }

            // Keeping track of this group's length.
            $length += mb_strlen((string) $list->tokens[$idx]->value, 'UTF-8');
        }

        return $length;
    }

    /**
     * Checks if a token is a statement or a clause inside a statement.
     *
     * @param Token $token the token to be checked
     *
     * @return int|false
     * @psalm-return 1|2|false
     */
    private static function isClause(Token $token): int|false
    {
        if (
            ($token->type === Token::TYPE_KEYWORD && isset(Parser::$STATEMENT_PARSERS[$token->keyword]))
            || ($token->type === Token::TYPE_NONE && strtoupper($token->token) === 'DELIMITER')
        ) {
            return 2;
        }

        if ($token->type === Token::TYPE_KEYWORD && isset(Parser::$KEYWORD_PARSERS[$token->keyword])) {
            return 1;
        }

        return false;
    }
}
