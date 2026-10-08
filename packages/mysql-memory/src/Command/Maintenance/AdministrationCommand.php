<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Maintenance;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\PreloadedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Executes the table maintenance and key cache statements: CHECK, OPTIMIZE, REPAIR and ANALYZE TABLE, CACHE INDEX and LOAD INDEX INTO CACHE.
 *
 * Each statement answers rows of Table, Op, Msg_type and Msg_text for each table in written
 * order, after it commits the open transaction. A table that does not exist is an Error row and a
 * failed status; a database that does not exist is an Error row and a "Corrupt" error. The tables
 * are InnoDB tables: CHECK and ANALYZE report OK, OPTIMIZE recreates the table, and REPAIR, CACHE
 * INDEX and LOAD INDEX are notes that the engine does not support them. A view is checked, and
 * the other operations refuse it as no base table. CACHE INDEX names DEFAULT,
 * the only key cache, and a table is never partitioned. ANALYZE TABLE with a histogram clause
 * changes the histograms (Histograms). Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/check-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/optimize-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/repair-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/cache-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html.
 *
 * @visibility MySqlMemory
 */
final class AdministrationCommand implements Command
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
     * Runs the operation on each table and answers its rows.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof CheckTable || $statement instanceof OptimizeTable || $statement instanceof RepairTable || $statement instanceof AnalyzeTable || $statement instanceof CacheIndex || $statement instanceof LoadIndex);
        if ($statement instanceof CacheIndex && $statement->cache !== null && strcasecmp($statement->cache->value, 'default') !== 0) {
            throw ErrorCode::UnknownKeyCache->error($statement->cache->value);
        }
        $histograms = new Histograms();
        if ($statement instanceof AnalyzeTable && $statement->histogram !== null) {
            $histograms->check($statement->histogram);
        }
        $names = $this->names($statement);
        $database = $session->variables->database;
        foreach ($names as [$name]) {
            if ($name->schema === null && $database === '') {
                throw ErrorCode::NoDatabase->error();
            }
        }
        $session->transaction->commit();
        if ($statement instanceof AnalyzeTable && $statement->histogram !== null && count($names) > 1) {
            return new ResultSet(self::columns($session), [['', 'histogram', 'Error', 'Only one table can be specified while modifying histogram statistics.']]);
        }
        $operation = $this->operation($statement);
        $rows = [];
        foreach ($names as [$name, $partitioned]) {
            $schema = $name->schema->value ?? $database;
            $label = $schema . '.' . $name->name->value;
            $table = $session->instance->dictionary->table($schema, $name->name->value);
            $failure = $this->failure($session, $schema, $name, $table);
            $view = $table === null && isset($session->instance->dictionary->schema($schema)?->views[$name->name->value]);
            if ($view) {
                foreach ($this->view($operation, $label, $statement instanceof AnalyzeTable && $statement->histogram !== null) as [$type, $text]) {
                    $rows[] = [$label, $statement instanceof AnalyzeTable && $statement->histogram !== null ? 'histogram' : $operation, $type, $text];
                }

                continue;
            }
            if ($statement instanceof AnalyzeTable && $statement->histogram !== null) {
                $found = $failure === null && $table !== null ? $histograms->rows($statement->histogram, $table) : [['Error', $failure ?? '']];
                foreach ($found as [$type, $text]) {
                    $rows[] = [$label, 'histogram', $type, $text];
                }

                continue;
            }
            if ($failure === null && $partitioned) {
                $failure = 'Partition management on a not partitioned table is not possible';
            }
            foreach ($failure === null ? $this->outcome($operation) : [['Error', $failure], $session->instance->dictionary->schema($schema) === null ? ['error', 'Corrupt'] : ['status', 'Operation failed']] as [$type, $text]) {
                $rows[] = [$label, $operation, $type, $text];
            }
        }

        return new ResultSet(self::columns($session), $rows);
    }

    /**
     * Answers the tables a statement names, each with whether it selects partitions.
     *
     * @return list<array{QualifiedName, bool}>
     */
    public function names(CheckTable|OptimizeTable|RepairTable|AnalyzeTable|CacheIndex|LoadIndex $statement): array
    {
        $names = [];
        foreach ($statement->tables as $table) {
            $names[] = $table instanceof CachedTable || $table instanceof PreloadedTable ? [$table->table, $table->partitions !== null] : [$table->name, false];
        }

        return $names;
    }

    /**
     * Answers the name of the operation a statement reports in its Op column.
     */
    public function operation(CheckTable|OptimizeTable|RepairTable|AnalyzeTable|CacheIndex|LoadIndex $statement): string
    {
        return match (true) {
            $statement instanceof CheckTable => 'check',
            $statement instanceof OptimizeTable => 'optimize',
            $statement instanceof RepairTable => 'repair',
            $statement instanceof AnalyzeTable => 'analyze',
            $statement instanceof CacheIndex => 'assign_to_keycache',
            default => 'preload_keys',
        };
    }

    /**
     * Answers why a table cannot be opened, or null when it can: its database or the table does not exist.
     */
    public function failure(Session $session, string $schema, QualifiedName $name, ?StoredTable $table): ?string
    {
        if ($session->instance->dictionary->schema($schema) === null) {
            return ErrorCode::BadDatabase->message($schema);
        }

        return $table === null ? ErrorCode::NoSuchTable->message($schema, $name->name->value) : null;
    }

    /**
     * Answers the messages an operation reports for a view: CHECK TABLE checks it, and the other operations need a base table.
     *
     * @return list<array{string, string}>
     */
    public function view(string $operation, string $label, bool $histogram): array
    {
        if ($histogram) {
            return [['Error', 'Cannot create histogram statistics for a view.']];
        }
        if ($operation === 'check') {
            return [['status', 'OK']];
        }
        [$schema, $name] = explode('.', $label, 2);

        return [['Error', ErrorCode::WrongObject->message($schema, $name, 'BASE TABLE')], ['status', 'Operation failed']];
    }

    /**
     * Answers the messages an operation reports for an InnoDB table, as Msg_type and Msg_text.
     *
     * @return list<array{string, string}>
     */
    public function outcome(string $operation): array
    {
        return match ($operation) {
            'check', 'analyze' => [['status', 'OK']],
            'optimize' => [['note', 'Table does not support optimize, doing recreate + analyze instead'], ['status', 'OK']],
            default => [['note', "The storage engine for the table doesn't support " . $operation]],
        };
    }

    /**
     * Answers the columns of the rows: Table, Op, Msg_type and Msg_text, in the character set of the results.
     *
     * @return list<ResultColumn>
     */
    public static function columns(Session $session): array
    {
        $results = $session->variables->read('character_set_results');
        $charset = (is_string($results) ? Charset::named($results) : null) ?? Charset::known('utf8mb3');
        $collation = $charset->defaultCollation(GrammarRelease::MySql847)->id;

        return [
            new ResultColumn('Table', Field::VarString, 128 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Op', Field::VarString, 10 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Msg_type', Field::VarString, 10 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Msg_text', Field::MediumBlob, 393216 * $charset->maxLength, 31, 0, $collation),
        ];
    }
}
