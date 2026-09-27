<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * Which parameter markers a lexer reads as bound parameters.
 *
 * A server has its own markers, such as `?` or `$1`. A named placeholder,
 * `:name`, is the other common way to bind a parameter in an application,
 * and a server that has no such marker rejects it. The named syntax reads
 * `:name` as a parameter where the server itself would not; a server that
 * reads it already is unaffected.
 *
 * @visibility public
 *
 * @example Measuring a named placeholder
 *     \SqlParser\Lexer\ParameterSyntax::Named->namedLength('WHERE id = :id', 11) // => 3
 * @example Reading nothing but the server's own markers
 *     \SqlParser\Lexer\ParameterSyntax::Native->namedLength(':id', 0) // => 0
 */
enum ParameterSyntax
{
    /**
     * Only the markers the server itself reads.
     */
    case Native;

    /**
     * The server's markers and the named placeholder `:name`.
     */
    case Named;

    /**
     * Reports whether the text at an offset spells a named placeholder.
     *
     * A name is `:` followed by letters, digits and underscores; `::` and
     * `:=` are not names.
     *
     * @param string $source The SQL text
     * @param int $offset Where the colon is
     *
     * @return int The length of the marker, or 0 when none starts there
     */
    public function namedLength(string $source, int $offset): int
    {
        if ($this !== self::Named || preg_match('/\G(?<!:):[A-Za-z0-9_]+/', $source, $match, 0, $offset) !== 1) {
            return 0;
        }

        return strlen($match[0]);
    }
}
