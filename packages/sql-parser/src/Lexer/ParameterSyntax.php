<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * Which parameter markers a lexer reads as bound parameters.
 *
 * A server has its own markers, such as `?` or `$1`. PHP's PDO rewrites
 * `:name` and `?` into the server's markers before the text reaches it, so a
 * statement written for PDO is not in the server's language. The PDO syntax
 * reads those markers as parameters too, where the server would reject them;
 * a server that reads them itself is unaffected.
 *
 * @visibility public
 *
 * @example Measuring a PDO named parameter
 *     \SqlParser\Lexer\ParameterSyntax::Pdo->namedLength('WHERE id = :id', 11) // => 3
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
     * The server's markers, PDO's `:name`, and PDO's `?` where the server lacks it.
     */
    case Pdo;

    /**
     * Reports whether the text at an offset spells a PDO named parameter.
     *
     * A name is `:` followed by letters, digits and underscores, as PDO reads
     * it; `::` and `:=` are not names.
     *
     * @param string $source The SQL text
     * @param int $offset Where the colon is
     *
     * @return int The length of the marker, or 0 when none starts there
     */
    public function namedLength(string $source, int $offset): int
    {
        if ($this !== self::Pdo || preg_match('/\G(?<!:):[A-Za-z0-9_]+/', $source, $match, 0, $offset) !== 1) {
            return 0;
        }

        return strlen($match[0]);
    }
}
