<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A composite value whose fields are known: a row constructor or the whole row of a relation.
 *
 * A row constructor yields an anonymous `record` with fields `f1`, `f2`, …;
 * the whole row of a relation has the relation's row type, whose fields are
 * its columns. Selecting a field reads its slot.
 * Source: https://www.postgresql.org/docs/17/rowtypes.html, https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ROW-CONSTRUCTORS.
 *
 * @visibility public
 * @example Reading the fields of a row constructor
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ROW(1, 2)');
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->type->descriptor->fields[1]->name->value] // => ['record', 'f2']
 */
final class Composite implements TypeDescriptor
{
    use Snapshot;

    /**
     * @var list<OutputSlot> The fields in order
     */
    public readonly array $fields;

    /**
     * @param list<OutputSlot> $fields The fields in order
     * @param Name|null $relation The relation whose row type this is, or null for an anonymous record
     */
    public function __construct(array $fields, public readonly ?Name $relation = null)
    {
        $this->fields = Check::listOf($fields, OutputSlot::class, 'A composite holds an ordered list of fields.');
    }

    /**
     * Answers the type name: the relation's name, or `record`.
     */
    public function name(): string
    {
        return $this->relation === null ? 'record' : $this->relation->value;
    }
}
