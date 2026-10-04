<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Declares the system columns every table has.
 *
 * Rule: PG-SYSTEM-COLUMNS-001. "Every table has several system columns that
 * are implicitly defined by the system": `tableoid` (oid), `xmin` (xid),
 * `cmin` (cid), `xmax` (xid), `cmax` (cid) and `ctid` (tid). They are found
 * by name only, are not part of `*`, and are never NULL. A user column
 * cannot take one of these names. Every relation with storage has them
 * (tables, partitioned and foreign tables, materialized views, sequences);
 * views do not. Source:
 * https://www.postgresql.org/docs/17/ddl-system-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SystemColumns
{
    /**
     * The system columns and their types, in attribute number order from -1 down.
     */
    private const COLUMNS = ['ctid' => Builtin::Tid, 'xmin' => Builtin::Xid, 'cmin' => Builtin::Cid, 'xmax' => Builtin::Xid, 'cmax' => Builtin::Cid, 'tableoid' => Builtin::Oid];

    /**
     * Answers the implicit columns of a relation with storage.
     *
     * @return list<ImplicitColumn>
     */
    public function implicit(): array
    {
        $columns = [];
        foreach (array_reverse(self::COLUMNS) as $name => $type) {
            $columns[] = new ImplicitColumn([new Name($name)], new Column(new Name($name), $type, Nullability::NotNull));
        }

        return $columns;
    }

    /**
     * Tells whether a column name is the name of a system column.
     */
    public function reserved(Name $name): bool
    {
        return isset(self::COLUMNS[$name->value]);
    }
}
