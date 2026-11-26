<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Shape;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Several output fields have the looked-up name; none is chosen.
 *
 * @visibility SqlSemantics
 */
final class AmbiguousFields implements FieldLookup
{
    use Snapshot;

    /**
     * @var list<Field> The fields with the name, in output order
     */
    public readonly array $fields;

    /**
     * @param string $name The looked-up name
     * @param list<Field> $fields The fields with the name, in output order; at least two
     */
    public function __construct(public readonly string $name, array $fields)
    {
        $this->fields = Check::listOf($fields, Field::class, 'An ambiguous lookup has at least two fields.', 2);
    }
}
