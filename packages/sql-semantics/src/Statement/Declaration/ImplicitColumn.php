<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column a table has without declaring it, such as a row identifier or a system column.
 *
 * It is found by any of its names when no declared column has that name, and
 * it is not part of `*`. When it is another name for a declared column, the
 * reference resolves to that declared column.
 *
 * @visibility public
 * @example Declaring an implicit row identifier
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a TEXT)')->declarations()[0];
 *     $table->implicit[0]->names[0]->value // => 'rowid'
 */
final class ImplicitColumn
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> Every name that finds the column
     */
    public readonly array $names;

    /**
     * @param list<Name> $names Every name that finds the column; at least one
     * @param Column $column The column a reference resolves to; a declared column of the table when the implicit column is an alias of it
     */
    public function __construct(array $names, public readonly Column $column)
    {
        $this->names = Check::listOf($names, Name::class, 'An implicit column has at least one name.', 1);
    }
}
