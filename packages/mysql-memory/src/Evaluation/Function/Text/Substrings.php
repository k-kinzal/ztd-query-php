<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;

/**
 * The string functions that take or replace a part of a string: LEFT, RIGHT, SUBSTRING and its synonyms SUBSTR and MID, SUBSTRING_INDEX and INSERT.
 *
 * Positions and lengths count characters in the character set of the result; a position counts
 * from 1. A result longer than max_allowed_packet is NULL with a warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Substrings
{
    /**
     * @param Strings $strings Reads the arguments as text of the result and counts its characters
     */
    public function __construct(public readonly Strings $strings = new Strings())
    {
    }

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('LEFT', 2, 2, $this->left(...)),
            new Routine('RIGHT', 2, 2, $this->right(...)),
            new Routine('SUBSTRING', 2, 3, $this->substring(...)),
            new Routine('SUBSTR', 2, 3, $this->substring(...)),
            new Routine('MID', 3, 3, $this->substring(...)),
            new Routine('SUBSTRING_INDEX', 3, 3, $this->substringIndex(...)),
            new Routine('INSERT', 4, 4, $this->insert(...)),
        ];
    }

    /**
     * LEFT: the leftmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function left(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);
        $count = $this->strings->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        return $count <= 0 ? '' : $this->strings->slice($text, 0, $count, $result);
    }

    /**
     * RIGHT: the rightmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function right(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);
        $count = $this->strings->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        $length = $this->strings->count($text, $result);

        return $count <= 0 ? '' : $this->strings->slice($text, max(0, $length - $count), null, $result);
    }

    /**
     * SUBSTRING(text, position[, length]): characters from a position counted from 1, or from the end when negative.
     *
     * @param list<Evaluable> $arguments
     */
    public function substring(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);
        $position = $this->strings->number($frame, $arguments[1]);
        $length = isset($arguments[2]) ? $this->strings->number($frame, $arguments[2]) : PHP_INT_MAX;
        if ($text === null || $position === null || $length === null) {
            return null;
        }
        $count = $this->strings->count($text, $result);
        if ($position === 0 || $length <= 0 || $position > $count || $position < -$count) {
            return '';
        }
        $start = $position > 0 ? $position - 1 : $count + $position;

        return $this->strings->slice($text, $start, $length === PHP_INT_MAX ? null : $length, $result);
    }

    /**
     * SUBSTRING_INDEX: the text before a number of occurrences of a delimiter, or after them from the end.
     *
     * @param list<Evaluable> $arguments
     */
    public function substringIndex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);
        $delimiter = $this->strings->text($frame, $arguments[1], $result);
        $count = $this->strings->number($frame, $arguments[2]);
        if ($text === null || $delimiter === null || $count === null) {
            return null;
        }
        if ($delimiter === '' || $count === 0) {
            return '';
        }
        $parts = explode($delimiter, $text);

        return $count > 0 ? implode($delimiter, array_slice($parts, 0, $count)) : implode($delimiter, array_slice($parts, max(0, count($parts) + $count)));
    }

    /**
     * INSERT(text, position, length, new): the text with characters from a position replaced.
     *
     * A result that exceeds max_allowed_packet is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function insert(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);
        $position = $this->strings->number($frame, $arguments[1]);
        $length = $this->strings->number($frame, $arguments[2]);
        $new = $this->strings->text($frame, $arguments[3], $result);
        if ($text === null || $position === null || $length === null || $new === null) {
            return null;
        }
        $count = $this->strings->count($text, $result);
        if ($position < 1 || $position > $count) {
            return $text;
        }
        $head = $this->strings->slice($text, 0, $position - 1, $result);
        $tail = $length < 0 || $length > $count ? '' : $this->strings->slice($text, $position - 1 + $length, null, $result);

        return $this->strings->fits($frame, strlen($head) + strlen($new) + strlen($tail), 'insert') ? $head . $new . $tail : null;
    }
}
