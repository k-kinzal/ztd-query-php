<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

/**
 * The written mode of a routine parameter.
 *
 * Mirrors PostgreSQL's `FunctionParameterMode` as written: `INOUT` and
 * `IN OUT` are the same mode spelled with one or two keywords, and both are
 * kept so that the statement is written back as it was. A parameter written
 * without a mode is an input parameter.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Telling the input modes
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode::InAndOut->input(), \SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode::Out->input()] // => [true, false]
 */
enum ParameterMode: string
{
    case In = 'IN';
    case Out = 'OUT';
    case InOut = 'INOUT';
    case InAndOut = 'IN OUT';
    case Variadic = 'VARIADIC';

    /**
     * Tells whether the caller passes a value for the parameter.
     */
    public function input(): bool
    {
        return $this !== self::Out;
    }

    /**
     * Tells whether the parameter is part of the result.
     */
    public function output(): bool
    {
        return $this === self::Out || $this === self::InOut || $this === self::InAndOut;
    }

    /**
     * Answers the keywords that spell the mode.
     *
     * @return non-empty-list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
