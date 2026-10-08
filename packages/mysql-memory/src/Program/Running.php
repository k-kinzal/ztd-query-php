<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * The statements a session runs, as its stored programs see them: the tables each uses, and the replies a CALL answered before it failed.
 *
 * A statement of a stored function or a trigger that writes a table a statement invoking it
 * reads or writes is ER_CANT_UPDATE_USED_TABLE_IN_SF_OR_TRG (verified on a live 8.4 server). The
 * result sets a procedure answered come before the error that ends it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 *
 * @visibility MySqlMemory
 */
final class Running
{
    /**
     * @var list<list<string>> The tables each running statement uses, the outermost first, each as `database.table`
     */
    public array $using = [];

    /**
     * @var list<\MySqlMemory\Result\Reply> The replies a statement answered before it failed, which the client receives before the error, as the result sets of a procedure CALL
     */
    public array $replies = [];

    /**
     * Answers the tables a statement uses, each as `database.table`.
     *
     * @return list<string>
     */
    public function used(Node $statement, string $database): array
    {
        $names = [];
        foreach ([...(new Walker())->find($statement, TableReference::class), ...(new Walker())->find($statement, WriteTarget::class)] as $table) {
            $names[] = $this->key($table->name, $database);
        }

        return array_values(array_unique($names));
    }

    /**
     * Refuses a statement of a stored function or trigger that writes a table a statement invoking it uses.
     *
     * @throws SqlError When it writes such a table
     */
    public function check(Node $statement, string $database): void
    {
        $targets = match (true) {
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => [$statement->into->table->name],
            $statement instanceof Delete => [$statement->table->name],
            $statement instanceof Update => array_map(static fn (TableReference $table): QualifiedName => $table->name, array_merge(...array_map(static fn (Node $relation): array => (new Walker())->find($relation, TableReference::class), $statement->tables))),
            default => [],
        };
        foreach ($targets as $target) {
            foreach ($this->using as $tables) {
                if (in_array($this->key($target, $database), $tables, true)) {
                    throw ProgramError::UsedTableInProgram->error($target->name->value);
                }
            }
        }
    }

    /**
     * Answers the key of a table name.
     */
    public function key(QualifiedName $name, string $database): string
    {
        return strtolower(($name->schema->value ?? $database) . '.' . $name->name->value);
    }
}
