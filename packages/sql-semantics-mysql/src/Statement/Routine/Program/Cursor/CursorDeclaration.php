<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * DECLARE ... CURSOR FOR: a named cursor over the rows of a query.
 *
 * The query is derived where the cursor is declared, with the variables
 * declared before it in scope (MYSQL-PROGRAM-BLOCK-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-cursor.html.
 *
 * @visibility public
 * @example Reading a cursor declaration
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT a FROM t; END');
 *     $create->statement->body->declarations[0]->name->value // => 'c'
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
