<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\FieldType;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW TABLES: the tables of a database, in name order, optionally filtered by LIKE.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ShowTablesCommand implements Command
{
    /**
     * Answers true.
     */
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the tables.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowTables);
        $name = $statement->database?->value ?? $session->variables->database;
        if ($name === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($name);
        }
        $names = array_keys($schema->tables);
        sort($names, SORT_STRING);
        if ($statement->filter instanceof ShowLike) {
            $pattern = '/\A' . strtr(preg_quote($statement->filter->pattern->value, '/'), ['%' => '.*', '_' => '.']) . '\z/s';
            $names = array_values(array_filter($names, static fn (string $table): bool => preg_match($pattern, $table) === 1));
        }
        $column = new ResultColumn('Tables_in_' . $name, FieldType::VarString, 256, 0, ColumnFlag::NotNull->value, Collation::Utf8mb3GeneralCi->id(), 'TABLE_NAME', 'TABLES', 'TABLES', 'information_schema');

        return new ResultSet([$column], array_map(static fn (string $table): array => [$table], $names));
    }
}
