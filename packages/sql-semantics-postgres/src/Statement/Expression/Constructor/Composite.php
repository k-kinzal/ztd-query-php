<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeDescriptor;

/**
 * A composite value whose fields are known: a row constructor or the whole row of a relation.
 *
 * A row constructor yields an anonymous `record` with fields `f1`, `f2`, …;
 * the whole row of a relation has the relation's row type, whose fields are
 * its columns. Selecting a field reads its slot.
 * Source: https://www.postgresql.org/docs/17/rowtypes.html, https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ROW-CONSTRUCTORS.
 *
 * @visibility SqlSemantics
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
