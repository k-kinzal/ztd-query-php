<?php

declare(strict_types=1);

namespace Requirements\Ears;

use Requirements\Input\InvalidInputException;

/**
 * Replaces quoted and code literals with filler so their words are not read as clauses.
 *
 * Double quotes, single quotes and backticks open a literal; an apostrophe after a letter or
 * digit is part of a word. Every masked character becomes "x", so the length is kept.
 */
final class LiteralMask
{
    /**
     * Masks every literal in a statement.
     *
     * @param string $text The statement
     *
     * @return string The statement with literal characters replaced by "x"
     *
     * @throws InvalidInputException When a literal is not closed
     */
    public function apply(string $text): string
    {
        $result = '';
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $character = $text[$i];
            if ($quote !== null) {
                if ($character === '\\' && $i + 1 < $length) {
                    $result .= 'xx';
                    ++$i;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                $result .= 'x';
                continue;
            }
            $apostrophe = $character === "'" && $i > 0 && ctype_alnum($text[$i - 1]);
            if (in_array($character, ['"', "'", '`'], true) && !$apostrophe) {
                $quote = $character;
                $result .= 'x';
            } else {
                $result .= $character;
            }
        }
        if ($quote !== null) {
            throw new InvalidInputException('EARS: close quoted or code literals.');
        }
        return $result;
    }
}
