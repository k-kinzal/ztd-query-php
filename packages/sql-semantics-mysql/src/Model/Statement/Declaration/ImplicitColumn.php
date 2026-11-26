<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A column a table has without declaring it, such as a row identifier or a system column.
 *
 * It is found by any of its names when no declared column has that name, and
 * it is not part of `*`. When it is another name for a declared column, the
 * reference resolves to that declared column.
 *
 * @visibility SqlSemantics
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
