<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A new table name that a declared table, or a table an earlier rename of the same statement created, already has.
 *
 * The server rejects the rename with ER_TABLE_EXISTS_ERROR.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))->message() // => 'Table t already exists.'
 */
final class TableExists implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The new name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Table ' . ($this->name->schema === null ? '' : $this->name->schema->value . '.') . $this->name->name->value . ' already exists.';
    }
}
