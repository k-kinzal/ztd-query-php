<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Type modifiers the named type does not accept.
 *
 * The server reports this when a type name is resolved: a modifier that is
 * not a simple constant, a modifier count or value outside what the type
 * takes, or modifiers on a type that takes none.
 * Source: https://www.postgresql.org/docs/17/datatype.html.
 *
 * @visibility public
 * @example Reporting a precision the numeric type does not allow
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT numeric(0) '1'");
 *     $query->field(0)->type->cause->message() // => 'Invalid type modifier for type numeric.'
 */
final class ModifierProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $typeName The displayed name of the type the modifiers were written for
     */
    public function __construct(public readonly string $typeName)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Invalid type modifier for type ' . $this->typeName . '.';
    }
}
