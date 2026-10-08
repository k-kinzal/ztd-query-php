<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;

/**
 * Executes EXPLAIN of a statement: the plan the server would execute it by.
 *
 * Before it prepares the statement the server checks the format: one it does not know is
 * ER_UNKNOWN_EXPLAIN_FORMAT; EXPLAIN ANALYZE runs the TREE format only; EXPLAIN INTO stores a
 * JSON plan and refuses an implicit or other format. It then makes the database of FOR SCHEMA
 * current, which must exist. A traditional plan has a row for each table the statement reads,
 * scanned whole; TREE and JSON plans are one document. The plans of the server come from its
 * optimizer and its cost model, which the emulator does not have, so only the plan of a
 * statement without tables is the server's. EXPLAIN FOR CONNECTION explains the statement
 * another connection is running.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html,
 * https://dev.mysql.com/doc/refman/8.4/en/explain-output.html.
 *
 * @visibility MySqlMemory
 */
final class ExplainCommand implements Command
{
    /**
     * The formats the server knows.
     */
    public const FORMATS = ['TRADITIONAL', 'JSON', 'TREE'];

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Raises the error of the format and options of an EXPLAIN, before the server prepares the statement it explains.
     *
     * For an EXPLAIN FOR CONNECTION, ANALYZE is refused once the format and INTO are checked.
     *
     * @throws \MySqlMemory\Error\SqlError When the server refuses the format or the options
     */
    public function check(Node $statement, Session $session): void
    {
        if (!$statement instanceof Explain && !$statement instanceof ExplainConnection) {
            return;
        }
        $format = $statement->format === null ? null : strtoupper($statement->format->value);
        if ($statement->format !== null && !in_array($format, self::FORMATS, true)) {
            throw StatementError::UnknownExplainFormat->error($statement->format->value);
        }
        if ($statement->analyze && $statement instanceof Explain && $format !== null && $format !== 'TREE') {
            throw StatementError::NotSupportedYet->error('EXPLAIN ANALYZE with ' . $format . ' format');
        }
        if ($statement->into !== null && $format !== 'JSON') {
            throw $format === null ? StatementError::ExplainIntoImplicitFormat->error() : StatementError::ExplainIntoFormat->error($format);
        }
        if ($statement instanceof ExplainConnection) {
            if ($statement->into !== null) {
                throw StatementError::ExplainIntoForConnection->error();
            }
            if ($statement->analyze) {
                throw StatementError::NotSupportedYet->error('EXPLAIN ANALYZE FOR CONNECTION');
            }

            return;
        }
        if ($statement->database !== null && $session->instance->dictionary->schema($statement->database->value) === null) {
            throw QueryError::BadDatabase->error($statement->database->value);
        }
    }

    /**
     * Explains the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Explain || $statement instanceof ExplainConnection);
        if ($statement instanceof ExplainConnection) {
            return $this->connection($statement, $session, $context);
        }
        $format = $statement->analyze ? 'TREE' : strtoupper($statement->format->value ?? (string) $session->variables->read('explain_format'));
        $database = $statement->database->value ?? $session->variables->database;
        $tables = $this->tables($statement->statement, $database);
        if ($statement->into !== null) {
            $session->variables->assign($statement->into->name->value, $this->document($tables), Domain::string(50331645, Collation::known('utf8mb3_general_ci'), Field::MediumBlob));

            return new Completion(0, 0, $context->diagnostics->count());
        }
        if ($format === 'TRADITIONAL') {
            return (new Listing($this->headings()))->sent($this->rows($statement, $session, $database), $context);
        }
        $text = $format === 'JSON' ? $this->document($tables) : $this->tree($tables);

        return (new Listing([Heading::text('EXPLAIN', Field::VarString, 78, ColumnFlag::NotNull->value, 31)]))->sent([[$text]], $context);
    }

    /**
     * Explains the statement another connection runs: none of the idle connections of the emulator runs one.
     *
     * A connection id no session has is ER_NO_SUCH_THREAD; the connection running the EXPLAIN
     * itself runs no statement the server explains (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the connection does not exist or is the session's own
     */
    public function connection(ExplainConnection $statement, Session $session, Context $context): Reply
    {
        $id = $statement->connection->hexadecimal ? (int) hexdec($statement->connection->text) : (int) $statement->connection->text;
        if ($id === $session->id) {
            throw StatementError::ExplainNotSupported->error();
        }
        if ($id < 1 || $id > $session->instance->connections()) {
            throw AdministrationError::NoSuchThread->error($statement->connection->text);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Answers the tables a statement reads, each by the name it is read through and its database.
     *
     * @return list<array{string, string, string}>
     */
    public function tables(Node $statement, string $database): array
    {
        $tables = [];
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            $name = match (true) {
                $node instanceof TableReference => [$node->name(), ($node->alias() ?? $node->name()->name)->value],
                $node instanceof ExplicitTable => [$node->table, $node->table->name->value],
                default => null,
            };
            if ($name !== null) {
                $tables[] = [$name[1], $name[0]->schema->value ?? $database, $name[0]->name->value];
            }
        }

        return $tables;
    }

    /**
     * Answers the rows of a traditional plan.
     *
     * @return list<list<int|string|null>>
     */
    public function rows(Explain $explain, Session $session, string $database): array
    {
        $statement = $explain->statement;
        $rows = [];
        $written = match (true) {
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => $statement->into,
            default => null,
        };
        if ($written !== null) {
            $rows[] = [1, $written->replace ? 'REPLACE' : 'INSERT', $written->table->name->name->value, null, 'ALL', null, null, null, null, null, null, null];
        }
        $kind = match (true) {
            $statement instanceof Update => 'UPDATE',
            $statement instanceof Delete => 'DELETE',
            default => 'SIMPLE',
        };
        $where = $statement instanceof Update || $statement instanceof Delete ? $statement->where : null;
        if ($statement instanceof Delete) {
            $rows[] = [1, 'DELETE', $statement->table->alias->value ?? $statement->table->name->name->value, null, 'ALL', null, null, null, null, $this->count($session, $statement->table->name, $database), '100.00', $where === null ? null : 'Using where'];

            return $rows;
        }
        foreach ($this->tables($statement, $database) as [$alias, $schema, $name]) {
            $rows[] = [1, $kind, $alias, null, 'ALL', null, null, null, null, $this->count($session, new QualifiedName(new Name($name), new Name($schema)), $database), '100.00', $where === null ? null : 'Using where'];
        }
        if ($rows === []) {
            $rows[] = [1, 'SIMPLE', null, null, null, null, null, null, null, null, null, 'No tables used'];
        }

        return $rows;
    }

    /**
     * Answers the rows InnoDB estimates a table holds: its rows, at least one.
     */
    public function count(Session $session, QualifiedName $name, string $database): int
    {
        $table = $session->instance->dictionary->table($name->schema->value ?? $database, $name->name->value);

        return max(1, $table === null ? 1 : count($table->data->rows));
    }

    /**
     * Writes a TREE plan.
     *
     * @param list<array{string, string, string}> $tables
     */
    public function tree(array $tables): string
    {
        if ($tables === []) {
            return "-> Rows fetched before execution  (cost=0..0 rows=1)\n";
        }

        return implode('', array_map(static fn (array $table): string => '-> Table scan on ' . $table[0] . "\n", $tables));
    }

    /**
     * Writes a JSON plan.
     *
     * @param list<array{string, string, string}> $tables
     */
    public function document(array $tables): string
    {
        if ($tables === []) {
            return "{\n  \"query_block\": {\n    \"select_id\": 1,\n    \"message\": \"No tables used\"\n  }\n}";
        }
        $entries = array_map(static fn (array $table): string => "      {\n        \"table\": {\n          \"table_name\": " . json_encode($table[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ",\n          \"access_type\": \"ALL\"\n        }\n      }", $tables);

        return "{\n  \"query_block\": {\n    \"select_id\": 1,\n    \"nested_loop\": [\n" . implode(",\n", $entries) . "\n    ]\n  }\n}";
    }

    /**
     * Answers the columns of a traditional plan.
     *
     * @return list<Heading>
     */
    public function headings(): array
    {
        $flags = ColumnFlag::Binary->value | ColumnFlag::Numeric->value;

        return [
            new Heading('id', Field::LongLong, 4, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | $flags),
            Heading::text('select_type', Field::VarString, 19, ColumnFlag::NotNull->value, 31),
            Heading::text('table', Field::VarString, 64, 0, 31),
            Heading::text('partitions', Field::MediumBlob, 6316032, 0, 31),
            Heading::text('type', Field::VarString, 10, 0, 31),
            Heading::text('possible_keys', Field::VarString, 4096, 0, 31),
            Heading::text('key', Field::VarString, 64, 0, 31),
            Heading::text('key_len', Field::VarString, 4096, 0, 31),
            Heading::text('ref', Field::VarString, 1024, 0, 31),
            new Heading('rows', Field::LongLong, 11, ColumnFlag::Unsigned->value | $flags),
            new Heading('filtered', Field::Double, 4, $flags, 2),
            Heading::text('Extra', Field::VarString, 255, ColumnFlag::NotNull->value, 31),
        ];
    }
}
