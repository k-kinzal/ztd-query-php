<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Heap;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes DROP TABLE and TRUNCATE TABLE.
 *
 * DROP TABLE of missing tables names all of them in one error (ER_BAD_TABLE_ERROR); with IF
 * EXISTS each missing table is a note. TRUNCATE removes every row and resets AUTO_INCREMENT.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility MySqlMemory
 */
final class DropTableCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Drops or empties the tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        $dictionary = $session->instance->dictionary;
        $database = $session->variables->database;
        if ($statement instanceof TruncateTable) {
            $schema = $statement->table->schema?->value ?? $database;
            $table = $dictionary->table($schema, $statement->table->name->value);
            if ($table === null) {
                throw ErrorCode::NoSuchTable->error($schema, $statement->table->name->value);
            }
            $table->data = new Heap();

            return new Completion();
        }
        assert($statement instanceof DropTable);
        $missing = [];
        $found = [];
        foreach ($statement->tables as $target) {
            $name = $target->name;
            $schema = $name->schema?->value ?? $database;
            if ($dictionary->table($schema, $name->name->value) === null) {
                $missing[] = $schema . '.' . $name->name->value;
            } else {
                $found[] = [$schema, $name->name->value];
            }
        }
        if ($missing !== [] && !$statement->ifExists) {
            throw ErrorCode::BadTable->error(implode(',', $missing));
        }
        foreach ($missing as $name) {
            $context->note(ErrorCode::BadTable, $name);
        }
        foreach ($found as [$schema, $name]) {
            unset($dictionary->schemas[$schema]->tables[$name]);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
