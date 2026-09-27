<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlParser\Lexer\ParameterSyntax;

/**
 * Which parameter markers SQL is read with: the server's own, or those and the named placeholder.
 *
 * A server has its own markers, such as `?` or `$1`. A named placeholder,
 * `:name`, is the other common way an application binds a parameter, and a
 * server that has no such marker rejects it. Reading it is a dialect
 * extension: the statement is read as the server would read it once the
 * placeholder is bound.
 *
 * @visibility public
 * @example Selecting the named placeholder
 *     \SqlSemantics\Core\Parameters::Named->syntax()->name // => 'Named'
 */
enum Parameters
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
     * Answers the lexer syntax the markers are read with.
     */
    public function syntax(): ParameterSyntax
    {
        return match ($this) {
            self::Native => ParameterSyntax::Native,
            self::Named => ParameterSyntax::Named,
        };
    }
}
