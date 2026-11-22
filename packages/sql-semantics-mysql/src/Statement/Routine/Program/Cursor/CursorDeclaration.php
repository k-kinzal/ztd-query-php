<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;

/**
 * DECLARE ... CURSOR FOR: a named cursor over the rows of a query.
 *
 * The query is derived where the cursor is declared, with the variables
 * declared before it in scope (MYSQL-PROGRAM-BLOCK-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-cursor.html.
 *
 * @visibility SqlSemantics
 */
final class CursorDeclaration implements Declaration
{
    use Snapshot;

    /**
     * @param Name $name The cursor name
     * @param Query $query The query whose rows the cursor reads
     */
    public function __construct(public readonly Name $name, public readonly Query $query)
    {
    }

    /**
     * Writes the declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE')->name($this->name, NameUse::Identifier)->keyword('CURSOR', 'FOR')->node($this->query);
    }
}
