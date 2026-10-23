<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Maintenance;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\PreloadedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Executes the table maintenance and key cache statements: CHECK, OPTIMIZE, REPAIR and ANALYZE TABLE, CACHE INDEX and LOAD INDEX INTO CACHE.
 *
 * Each statement answers rows of Table, Op, Msg_type and Msg_text for each table in written
 * order, after it commits the open transaction. A table that does not exist is an Error row and a
 * failed status; a database that does not exist is an Error row and a "Corrupt" error (in MySQL 5.6
 * and 5.7 a missing table, verified on live 5.6.51 and 5.7.44 servers). For
 * InnoDB, CHECK and ANALYZE report OK, OPTIMIZE recreates the table, and REPAIR, CACHE
 * INDEX and LOAD INDEX are notes that the engine does not support them. MyISAM
 * accepts cache assignment and preloading. A view is checked, and
 * the other operations refuse it as no base table. CACHE INDEX names DEFAULT or
 * a case-sensitive cache initialized by a positive buffer allocation. Reducing
 * its buffer to zero retains that identity. Partition checks use the held table definition. ANALYZE TABLE with a histogram clause
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
     *
     * Input warnings become rows of the first table, before its own report,
     * including histogram operations (verified on MySQL 5.6 through 9.1).
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof CheckTable || $statement instanceof OptimizeTable || $statement instanceof RepairTable || $statement instanceof AnalyzeTable || $statement instanceof CacheIndex || $statement instanceof LoadIndex);
        $histogram = $statement instanceof AnalyzeTable ? $statement->histogram : null;
        $names = $this->names($statement);
        $this->validate($statement, $names, $session);
        $session->transaction->commit();
        $operation = $histogram !== null ? 'histogram' : $this->operation($statement);
        $rows = $this->notices($names[0][0], $operation, $session);
        if ($histogram !== null && count($names) > 1) {
            return new ResultSet(self::columns($session), [...$rows, ['', 'histogram', 'Error', 'Only one table can be specified while modifying histogram statistics.']]);
        }
        foreach ($names as $position => [$name, $partitioned]) {
            $item = $statement->tables[$position];
            $cached = $item instanceof CachedTable || $item instanceof PreloadedTable;
            array_push($rows, ...$this->report($operation, $histogram, $name, $cached ? ($item->partitions ?? false) : $partitioned, $session, $cached ? $item->indexes : null));
        }

        return new ResultSet(self::columns($session), $rows);
    }

    /**
     * Answers the administration rows of ALTER TABLE partition maintenance.
     *
     * Table and partition failures are result rows, without adding conditions to SHOW WARNINGS.
     * Verified on MySQL 8.4.7 through SQL.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-maintenance.html.
     */
    public function partitions(MaintainPartitions $command, QualifiedName $name, Session $session): ResultSet
    {
        $operation = strtolower($command->kind->value);
        $rows = [...$this->notices($name, $operation, $session), ...$this->report($operation, null, $name, $command->partitions, $session)];

        return new ResultSet(self::columns($session), $rows);
    }

    /**
     * Moves input conditions into the first table's report and clears SHOW WARNINGS.
     *
     * @return list<array{string, string, string, string}> The already interpreted conditions
     */
    public function notices(QualifiedName $name, string $operation, Session $session): array
    {
        $label = ($name->schema->value ?? $session->variables->database) . '.' . $name->name->value;
        $rows = array_map(static fn (array $condition): array => [$label, $operation, $condition[0], $condition[2]], $session->diagnostics->conditions);
        $session->diagnostics->clear();
        return $rows;
    }

    /**
     * Requires a database for every table before checking key caches or repeated histogram
     * columns. These checks precede the implicit commit.
     *
     * @param list<array{QualifiedName, bool}> $names The tables the statement names
     * @throws \MySqlMemory\Error\SqlError When the statement is refused
     */
    public function validate(CheckTable|OptimizeTable|RepairTable|AnalyzeTable|CacheIndex|LoadIndex $statement, array $names, Session $session): void
    {
        foreach ($names as [$name]) {
            if ($name->schema === null && $session->variables->database === '') {
                throw QueryError::NoDatabase->error();
            }
        }
        if ($statement instanceof CacheIndex && $statement->cache !== null && $statement->cache->value !== 'default' && !isset($session->variables->globals->initializedCaches[$statement->cache->value])) {
            throw AdministrationError::UnknownKeyCache->error($statement->cache->value);
        }
        if ($statement instanceof AnalyzeTable && $statement->histogram !== null) {
            (new Histograms())->check($statement->histogram);
        }
    }

    /**
     * Runs the operation, or the histogram request, on one table and answers its rows.
     *
     * @param list<Name|PrimaryIndex>|null $indexes The selected keys of a cache request
     * @return list<array{string, string, string, string}>
     */
    public function report(string $operation, ?Histogram $histogram, QualifiedName $name, bool|PartitionSelection $partitioned, Session $session, ?array $indexes = null): array
    {
        $schema = $name->schema->value ?? $session->variables->database;
        $label = $schema . '.' . $name->name->value;
        $table = $session->instance->dictionary->table($schema, $name->name->value);
        $failure = $this->failure($session, $schema, $name, $table);
        $view = $table === null && isset($session->instance->dictionary->schema($schema)?->views[$name->name->value]);
        if ($histogram !== null) {
            return $this->rows($label, 'histogram', (new Histograms())->report($histogram, $table, $failure, $view, $session->instance->version));
        }
        if ($view) {
            return $this->rows($label, $operation, $this->view($operation, $label, false, $partitioned !== false));
        }
        if ($failure === null && $partitioned !== false && $table !== null) {
            $failure = $table->definition->partitioning === null ? 'Partition management on a not partitioned table is not possible' : null;
            foreach ($failure === null && $partitioned instanceof NamedPartitions ? $partitioned->names : [] as $partition) {
                if ($table->definition->partitioning?->partition($partition->value) === null) {
                    $failure = \MySqlMemory\Error\Family\PartitionError::PartitionListError->message($label);
                    break;
                }
            }
        }

        $failure ??= $this->keyFailure($table, $indexes);
        \MySqlMemory\Storage\IndexStatistics::refresh($table, $operation, $failure === null);

        return $this->rows($label, $operation, $failure === null ? $this->outcome($operation, $partitioned instanceof PartitionSelection, $table?->definition->engine ?? 'InnoDB') : [['Error', $failure], $session->instance->dictionary->schema($schema) === null && !$session->settings()->legacy() ? ['error', 'Corrupt'] : ['status', 'Operation failed']]);
    }

    /**
     * MyISAM resolves selected cache keys after table and partition checks.
     * InnoDB reports its unsupported-operation note without looking up keys.
     *
     * @param list<Name|PrimaryIndex>|null $indexes The selected keys, or all keys
     */
    public function keyFailure(?StoredTable $table, ?array $indexes): ?string
    {
        if ($table === null || strcasecmp($table->definition->engine, 'MyISAM') !== 0) {
            return null;
        }
        foreach ($indexes ?? [] as $index) {
            $name = $index instanceof PrimaryIndex ? 'PRIMARY' : $index->value;
            if (array_filter($table->definition->keys, static fn (Key $key): bool => strcasecmp($key->name, $name) === 0) === []) {
                return SchemaError::KeyMissing->message($name, $table->definition->name);
            }
        }
        return null;
    }

    /**
     * Answers the rows of a table: its label and the Op column before each Msg_type and Msg_text.
     *
     * @param list<array{string, string}> $messages
     * @return list<array{string, string, string, string}>
     */
    public function rows(string $label, string $operation, array $messages): array
    {
        $rows = [];
        foreach ($messages as [$type, $text]) {
            $rows[] = [$label, $operation, $type, $text];
        }

        return $rows;
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
            return \MySqlMemory\Session\Problem\Errors::unknown($schema, $name->name->value, $session->settings()->release())->getMessage();
        }

        return $table === null ? QueryError::NoSuchTable->message($schema, $name->name->value) : null;
    }

    /**
     * Answers the messages an operation reports for a view: CHECK TABLE checks it, and the other operations need a base table.
     *
     * @return list<array{string, string}>
     */
    public function view(string $operation, string $label, bool $histogram, bool $baseTable = false): array
    {
        if ($histogram) {
            return [['Error', 'Cannot create histogram statistics for a view.']];
        }
        if ($operation === 'check' && !$baseTable) {
            return [['status', 'OK']];
        }
        [$schema, $name] = explode('.', $label, 2);

        return [['Error', SchemaError::WrongObject->message($schema, $name, 'BASE TABLE')], ['status', 'Operation failed']];
    }

    /**
     * Answers engine-specific maintenance messages, as Msg_type and Msg_text.
     * MyISAM accepts cache assignment and index preloading; InnoDB reports its
     * unsupported-operation note. Verified on native 5.6 and 8.4 servers.
     *
     * @return list<array{string, string}>
     */
    public function outcome(string $operation, bool $partitioned = false, string $engine = 'InnoDB'): array
    {
        if (strcasecmp($engine, 'MyISAM') === 0 && in_array($operation, ['assign_to_keycache', 'preload_keys'], true)) {
            return [['status', 'OK']];
        }
        if ($partitioned && $operation === 'repair') {
            return [['status', 'OK']];
        }
        if ($partitioned && $operation === 'optimize') {
            return [['note', 'Table does not support optimize on partitions. All partitions will be rebuilt and analyzed.'], ['status', 'OK']];
        }

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
        $collation = ($session->variables->resultCollation() ?? $charset->defaultCollation($session->settings()->release()))->id;

        return [
            new ResultColumn('Table', Field::VarString, 128 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Op', Field::VarString, 10 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Msg_type', Field::VarString, 10 * $charset->maxLength, 31, 0, $collation),
            new ResultColumn('Msg_text', Field::MediumBlob, 393216 * ($results === null ? 1 : $charset->maxLength), 31, 0, $collation),
        ];
    }
}
