<?php

declare(strict_types=1);

namespace ZtdQuery\Sql\Profile;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;

/**
 * How one dialect writes a comment.
 *
 * A comment runs either to the end of the line or between a pair of
 * delimiters, and dialects differ in what spells each, in whether a prefix
 * only comments out the line when whitespace follows it, and in whether one
 * block comment may hold another.
 */
final class SqlCommentProfile
{
    /** @var list<non-empty-string> */
    private readonly array $lineCommentPrefixes;

    /** @var list<non-empty-string> */
    private readonly array $whitespaceDelimitedLineCommentPrefixes;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $blockCommentPairs;

    /**
     * @param list<string> $lineCommentPrefixes Prefixes that start a comment running to the end of the line
     * @param list<string> $whitespaceDelimitedLineCommentPrefixes Prefixes that do so only when whitespace follows
     * @param array<string, string> $blockCommentPairs Opening delimiter => the one that closes the comment
     * @param bool $nestedBlockComments Whether a block comment may contain another
     * @param LexicalDelimiters $delimiters Refuses lexical data a scanner could not use
     *
     * @throws InvalidDefinitionException When a delimiter is empty
     */
    public function __construct(
        array $lineCommentPrefixes,
        array $whitespaceDelimitedLineCommentPrefixes,
        array $blockCommentPairs,
        private readonly bool $nestedBlockComments,
        LexicalDelimiters $delimiters = new LexicalDelimiters(),
    ) {
        $this->lineCommentPrefixes = $delimiters->nonEmpty($lineCommentPrefixes);
        $this->whitespaceDelimitedLineCommentPrefixes = $delimiters->nonEmpty(
            $whitespaceDelimitedLineCommentPrefixes,
        );
        $this->blockCommentPairs = $delimiters->pairs($blockCommentPairs, 'Block comment');
    }

    /**
     * Reports whether a comment running to the end of the line starts here.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return bool True when one starts there
     */
    public function startsLineComment(string $sql, int $offset): bool
    {
        foreach ($this->lineCommentPrefixes as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) === 0) {
                return true;
            }
        }
        foreach ($this->whitespaceDelimitedLineCommentPrefixes as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) !== 0) {
                continue;
            }
            $following = $sql[$offset + strlen($prefix)] ?? '';
            if ($following === '' || ctype_space($following)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the block comment delimiters starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return array{non-empty-string, non-empty-string}|null The opening and closing delimiters, or null when no comment starts there
     */
    public function blockCommentAt(string $sql, int $offset): ?array
    {
        foreach ($this->blockCommentPairs as $opening => $closing) {
            if (substr_compare($sql, $opening, $offset, strlen($opening)) === 0) {
                return [$opening, $closing];
            }
        }

        return null;
    }

    /**
     * Reports whether a block comment may contain another.
     *
     * @return bool True when the dialect nests them
     */
    public function supportsNestedBlockComments(): bool
    {
        return $this->nestedBlockComments;
    }
}
