<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The name of a column at a position that defines or alters it, with the table qualifier MySQL 5.x allows there.
 *
 * `CREATE TABLE t (t.a INT)` and `ALTER TABLE t DROP COLUMN shop.t.a` write a
 * qualified column name; the server requires the qualifier to name the table
 * of the statement, so it is kept as written.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/identifier-qualifiers.html.
 *
 * @visibility public
 * @example Holding a qualified column name
 *     $name = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
 *     [$name->table?->name->value, $name->column->value] // => ['t', 'a']
 */
final class ColumnName implements Node
{
    use Snapshot;

    /**
     * @param Name $column The column name
     * @param QualifiedName|null $table The table qualifier, and its database when written
     */
    public function __construct(public readonly Name $column, public readonly ?QualifiedName $table = null)
    {
        Check::input($table === null || $table->catalog === null, 'A column name is qualified by a table and at most a database.');
    }

    /**
     * Writes the qualifier parts and the column name.
     */
    public function render(Output $out): void
    {
        if ($this->table?->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->table !== null) {
            $out->name($this->table->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->column, NameUse::Column);
    }
}
