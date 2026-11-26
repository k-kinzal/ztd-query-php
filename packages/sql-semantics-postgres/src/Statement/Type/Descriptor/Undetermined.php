<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeDescriptor;

/**
 * The type of a column defined by a query when that type depends on inputs the context does not declare.
 *
 * CREATE TABLE AS, SELECT INTO, CREATE VIEW and CREATE MATERIALIZED VIEW
 * give each column the type of its output expression. When that type
 * depends on a declaration the context does not hold (the result of an
 * undeclared routine, a column of an undeclared relation, a parameter
 * without a declared type), the column still exists under its name, and
 * its catalog type is the one those inputs would settle. The descriptor
 * keeps the inputs.
 * Source: https://www.postgresql.org/docs/17/sql-createtableas.html.
 *
 * @visibility SqlSemantics
 */
final class Undetermined implements TypeDescriptor
{
    use Snapshot;

    /**
     * @var non-empty-list<MissingInput> The inputs that would settle the type
     */
    public readonly array $missing;

    /**
     * @param list<MissingInput> $missing The inputs that would settle the type; at least one
     */
    public function __construct(array $missing)
    {
        $this->missing = Check::listOf($missing, MissingInput::class, 'An undetermined type depends on at least one missing input.', 1);
    }

    /**
     * Names the type by the inputs that would settle it.
     */
    public function name(): string
    {
        return 'the type settled by ' . implode(' and ', array_map(static fn (MissingInput $input): string => $input->describe(), $this->missing));
    }
}
