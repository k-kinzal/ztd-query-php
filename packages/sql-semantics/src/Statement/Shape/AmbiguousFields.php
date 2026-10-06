<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * Several output fields have the looked-up name; none is chosen.
 *
 * @visibility public
 * @example Finding every field that shares a name
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS a');
 *     count($operation->lookupField('a')->fields) // => 2
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
