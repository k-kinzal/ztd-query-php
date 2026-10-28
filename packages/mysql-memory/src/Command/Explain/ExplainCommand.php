<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
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
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

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
     * Raises the error of an EXPLAIN format the server does not know, which it finds before any other problem of the statement it explains, even one it finds while it reads it (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the format is unknown
     */
    public function format(Node $statement): void
    {
        if (($statement instanceof Explain || $statement instanceof DeferredExplain || $statement instanceof ExplainConnection) && $statement->format !== null && !in_array(strtoupper($statement->format->value), self::FORMATS, true)) {
            throw StatementError::UnknownExplainFormat->error($statement->format->value);
        }
    }

    /**
     * Raises the error of the format and options of an EXPLAIN, before the server prepares the statement it explains.
     *
     * Connection explanations retain refused options for the same versioned checks.
     *
     * @throws \MySqlMemory\Error\SqlError When the server refuses the format or the options
     */
    public function check(Node $statement, Session $session): void
    {
        if (!$statement instanceof Explain && !$statement instanceof DeferredExplain && !$statement instanceof ExplainConnection) {
            return;
        }
        (new Options())->check($statement, $session);
        if ($statement instanceof ExplainConnection) {
            return;
        }
        if ($statement->database !== null && $session->instance->dictionary->schema($statement->database->value) === null) {
            throw QueryError::BadDatabase->error($statement->database->value);
        }
    }

    /**
     * Checks an analysis format after the explained statement's dependencies prepare.
     *
     * @throws \MySqlMemory\Error\SqlError When the selected analysis report is unavailable
     */
    public function analysis(Explain|DeferredExplain $statement, Session $session): void
    {
        $format = strtoupper($statement->format->value ?? '');
        if ($statement->analyze && $format === 'JSON' && version_compare($session->instance->version, '9.0.0', '>=') && $session->variables->count('explain_json_format_version', 1) === 1) {
            throw StatementError::ExplainAnalyzeJsonVersion->error();
        }
        if ($statement->analyze && $format !== '' && $format !== 'TREE') {
            throw StatementError::NotSupportedYet->error('EXPLAIN ANALYZE with ' . $format . ' format');
        }
    }

    /**
     * Explains the statement.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof Explain || $statement instanceof ExplainConnection);
        if ($statement instanceof ExplainConnection) {
            return $this->connection($statement, $session, $context);
        }
        $this->analysis($statement, $session);
        $format = $statement->analyze ? 'TREE' : strtoupper($statement->format->value ?? (string) ($session->variables->read('explain_format') ?? 'TRADITIONAL'));
        $database = $statement->database->value ?? $session->variables->database;
        $tables = $this->tables($statement->statement, $database);
        (new \MySqlMemory\Session\Problem\Sampling())->optimized($statement->statement, $operation->statementInformation(), $session->settings(), $session->instance->dictionary);
        if ($statement->into !== null) {
            $session->variables->assign($statement->into->name->value, $this->document($tables), Domain::string(50331645, Collation::known('utf8mb3_general_ci'), Field::MediumBlob));

            return new Completion(0, 0, $context->diagnostics->raised());
        }
        $impossible = $this->impossible($operation, $session, $context, $connection);
        $this->note($operation, $session, $connection, $format, $impossible);
        if ($format === 'TRADITIONAL') {
            $rows = $this->rows($statement, $session, $database, $impossible);
            if ($impossible && !$session->settings()->legacy()) {
                $note = $this->deleted($statement, $database);
                if ($note !== null) {
                    $context->diagnostics->note(QueryError::RewrittenQuery, $note);
                }
            }
            $headings = $this->headings();
            if ($session->settings()->legacy()) {
                $headings = LegacyPlan::headings($headings, $statement->modifier, $session->instance->version);
                $rows = LegacyPlan::rows($rows, $statement, $session->instance->version);
            }

            return (new Listing($headings))->sent($rows, $context);
        }
        $text = (new MeasuredTable())->report($operation, $session, $context, $connection) ?? ($format === 'JSON' ? $this->document($tables) : $this->tree($tables));

        return (new Listing([Heading::text('EXPLAIN', Field::VarString, 78, ColumnFlag::NotNull->value, 31)]))->sent([[$text]], $context);
    }

    /**
     * Publishes rewritten-query notes for the release and output format.
     */
    public function note(MySqlStatement $operation, Session $session, Connection $connection, string $format, bool $impossible): void
    {
        $statement = $operation;
        if (!$statement instanceof Explain) {
            return;
        }
        if (($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 || $statement->modifier === \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Extended) && $format !== 'TREE') {
            $note = (new QueryNote())->projection($operation, $session, $connection, $impossible) ?? ($session->settings()->legacy() ? null : (new InsertNote())->rows($operation, $session));
            if ($note !== null) {
                $connection->context->diagnostics->note(QueryError::RewrittenQuery, $note);
            }
        }
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
        $id = $statement->connectionId;
        if ($id === $session->id) {
            throw StatementError::ExplainNotSupported->error();
        }
        $target = ($session->instance->sessions[$id] ?? null)?->get();
        if ($target === null || $target->released) {
            throw AdministrationError::NoSuchThread->error($id);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
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
    public function rows(Explain $explain, Session $session, string $database, bool $impossible = false): array
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
        $kind = $this->kind($statement);
        $where = $statement instanceof Update || $statement instanceof Delete || $statement instanceof MultipleDelete ? $statement->where : null;
        if ($impossible) {
            return [[1, $kind, null, null, null, null, null, null, null, null, null, 'Impossible WHERE']];
        }
        if ($statement instanceof Delete) {
            $rows[] = [1, 'DELETE', $statement->table->alias->value ?? $statement->table->name->name->value, null, 'ALL', null, null, null, null, $this->count($session, $statement->table->name, $database), '100.00', $where === null ? null : 'Using where'];

            return $rows;
        }
        foreach ($this->tables($statement, $database) as [$alias, $schema, $name]) {
            $scan = PrimaryScan::of($statement, $session->instance->dictionary->table($schema, $name, $session->temporaries)?->definition);
            $rows[] = [1, $kind, $alias, null, $scan[0] ?? 'ALL', null, $scan[1] ?? null, $scan[2] ?? null, null, $this->count($session, new QualifiedName(new Name($name), new Name($schema)), $database), '100.00', $scan[3] ?? ($where === null ? null : 'Using where')];
        }
        if ($rows === []) {
            $rows[] = [1, 'SIMPLE', null, null, null, null, null, null, null, null, null, 'No tables used'];
        }

        return $rows;
    }

    /**
     * Names the modification whose rows an explanation prepares.
     */
    public function kind(Node $statement): string
    {
        return match (true) {
            $statement instanceof Update => 'UPDATE',
            $statement instanceof Delete, $statement instanceof MultipleDelete => 'DELETE',
            default => 'SIMPLE',
        };
    }

    /**
     * Resolves a selection or DELETE predicate known before reading any table rows.
     * Uses semantic facts and the expression compiler; EXPLAIN never deletes rows.
     */
    public function impossible(MySqlStatement $operation, Session $session, Context $context, Connection $connection): bool
    {
        $explain = $operation;
        if (!$explain instanceof Explain) {
            return false;
        }
        $statement = $explain->statement instanceof QueryRoot ? $explain->statement->query : $explain->statement;
        if ((!$statement instanceof Delete && !$statement instanceof MultipleDelete && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select) || $statement->where === null) {
            return false;
        }
        $compiler = (new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session)))->compiler;
        if ($compiler->constancy($statement->where) !== Constancy::Resolved) {
            return false;
        }
        $predicate = $compiler->compile($statement->where, new Scope());

        return Convert::toPredicate($predicate->evaluate(new Frame($context)), $predicate->domain(), $context) !== true;
    }

    /**
     * Describes a DELETE whose constant predicate eliminates every row.
     * The rewritten-query note names resolved base tables, including aliased
     * deletion targets. Observed through EXPLAIN and SHOW WARNINGS on 8.0–9.1.
     */
    public function deleted(Explain $explain, string $database): ?string
    {
        $statement = $explain->statement;
        if (!$statement instanceof Delete && !$statement instanceof MultipleDelete) {
            return null;
        }
        $quote = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';
        $qualified = static fn (QualifiedName $name): string => $quote($name->schema->value ?? $database) . '.' . $quote($name->name->value);
        $prefix = 'delete';
        foreach ($statement->options as $option) {
            $prefix .= ' ' . strtolower($option->value);
        }
        if ($statement instanceof Delete) {
            return $prefix . ' from ' . $qualified($statement->table->name) . ($statement->table->alias === null ? '' : ' ' . $quote($statement->table->alias->value)) . ' where false';
        }
        $sources = [];
        $names = [];
        foreach ($statement->tables as $table) {
            if (!$table instanceof TableReference) {
                return null;
            }
            $name = $qualified($table->name());
            $sources[] = $name . ($table->alias() === null ? '' : ' ' . $quote($table->alias()->value));
            $names[strtolower(($table->alias() ?? $table->name()->name)->value)] = $name;
        }
        $targets = array_map(static fn (QualifiedName $target): string => $names[strtolower($target->name->value)] ?? $qualified($target), $statement->targets);

        return $prefix . ' ' . implode(',', $targets) . ' from ' . implode(',', $sources) . ' where false';
    }

    /**
     * Answers the rows InnoDB estimates a table holds: its rows, at least one.
     */
    public function count(Session $session, QualifiedName $name, string $database): int
    {
        $table = $session->instance->dictionary->table($name->schema->value ?? $database, $name->name->value, $session->temporaries);

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
