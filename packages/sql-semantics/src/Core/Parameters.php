<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlParser\Lexer\ParameterSyntax;

/**
 * Which parameter markers SQL is read with: the server's own, or the ones PDO rewrites for it.
 *
 * A server has its own markers, such as `?` or `$1`. PHP's PDO rewrites
 * `:name`, and `?` where the server lacks it, before the text reaches the
 * server, so a statement written for PDO is not in the server's language
 * until those markers are read as parameters too.
 *
 * @visibility public
 * @example Selecting the markers PDO rewrites
 *     \SqlSemantics\Core\Parameters::Pdo->syntax()->name // => 'Pdo'
 */
enum Parameters
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
     * Answers the lexer syntax the markers are read with.
     */
    public function syntax(): ParameterSyntax
    {
        return match ($this) {
            self::Native => ParameterSyntax::Native,
            self::Pdo => ParameterSyntax::Pdo,
        };
    }
}
