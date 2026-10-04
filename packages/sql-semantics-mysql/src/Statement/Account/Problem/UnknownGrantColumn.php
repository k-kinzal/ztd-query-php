<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A column of a column privilege that the completely declared table of GRANT does not have.
 *
 * The server rejects the statement with ER_BAD_FIELD_ERROR
 * (mysql_table_grant). REVOKE does not check the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-column-privileges.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn(new \SqlSemantics\Statement\Identifier\Name('c'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))->message() // => 'Column c does not exist in table t.'
 */
final class UnknownGrantColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column as written
     * @param QualifiedName $table The table as written
     */
    public function __construct(public readonly Name $column, public readonly QualifiedName $table)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->column->value . ' does not exist in table ' . ($this->table->schema === null ? '' : $this->table->schema->value . '.') . $this->table->name->value . '.';
    }
}
