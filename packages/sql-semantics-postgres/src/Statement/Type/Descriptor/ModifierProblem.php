<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Type modifiers the named type does not accept.
 *
 * The server reports this when a type name is resolved: a modifier that is
 * not a simple constant, a modifier count or value outside what the type
 * takes, or modifiers on a type that takes none.
 * Source: https://www.postgresql.org/docs/17/datatype.html.
 *
 * @visibility SqlSemantics
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
