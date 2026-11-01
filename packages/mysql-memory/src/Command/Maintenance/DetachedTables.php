<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Maintenance;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Tablespaces;

/**
 * Reports maintenance of InnoDB definitions whose files were discarded.
 *
 * Failed checks, analysis and repair close the cached handler. A modern failed
 * optimization reopens it, whereas a legacy recreation leaves it closed.
 * Legacy analysis has only the failed status; modern analysis reports each
 * missing file during both statistics reads. Verified on 5.6.51, 5.7.44 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html,
 * https://github.com/mysql/mysql-server/blob/mysql-8.4.7/storage/innobase/handler/ha_innodb.cc.
 *
 * @visibility MySqlMemory
 */
final class DetachedTables
{
    /**
     * @return list<array{string, string}>|null Maintenance messages, or the ordinary engine result
     */
    public function report(string $operation, StoredTable $table, Session $session): ?array
    {
        if ($table->discardedTablespaces === [] || !in_array($operation, ['check', 'analyze', 'optimize', 'repair'], true)) {
            return null;
        }
        $definition = $table->definition;
        $session->instance->dictionary->cache->close($definition->schema, $definition->name);
        return match ($operation) {
            'check' => [['Error', (new Tablespaces())->message($definition->name, $session->settings()->release())], ['error', 'Corrupt']],
            'analyze' => $this->analyze($table, $session),
            'optimize' => $this->optimize($table, $session),
            'repair' => null,
        };
    }

    /**
     * @return list<array{string, string}> The two statistics reads followed by the failed status
     */
    public function analyze(StoredTable $table, Session $session): array
    {
        $rows = [];
        $definition = $table->definition;
        $name = '`' . str_replace('`', '``', $definition->schema) . '`.`' . str_replace('`', '``', $definition->name) . '`';
        $partitions = $definition->partitioning === null ? [''] : array_map(static fn (\MySqlMemory\Dictionary\Partition\Partition $partition): string => $partition->name, array_filter($definition->partitioning->partitions, static fn (\MySqlMemory\Dictionary\Partition\Partition $partition): bool => isset($table->discardedTablespaces[strtolower($partition->name)])));
        foreach ($session->settings()->legacy() ? [] : [0, 1] as $read) {
            foreach ($partitions as $partition) {
                $prefix = $partition === '' ? 'table ' . $name : 'partition ' . $name . ' /* Partition `' . str_replace('`', '``', $partition) . '` */';
                $suffix = $partition === '' ? 'the free space to zero. (errno: 11 - Resource temporarily unavailable)' : 'the free space of the partition to zero.';
                $rows[] = ['Warning', 'InnoDB: Trying to get the free space for ' . $prefix . ' but its tablespace has been discarded or the .ibd file is missing. Setting ' . $suffix];
            }
        }
        return [...$rows, ['status', 'Operation failed']];
    }

    /**
     * @return list<array{string, string}> The recreation error, also retained in the diagnostics area
     */
    public function optimize(StoredTable $table, Session $session): array
    {
        $definition = $table->definition;
        $warning = 'InnoDB: ' . (new Tablespaces())->message($definition->name, $session->settings()->release());
        $session->diagnostics->warning(SchemaError::TablespaceDiscarded, $warning);
        if ($session->settings()->legacy()) {
            $session->diagnostics->warning(SchemaError::TablespaceDiscarded, $warning);
        } else {
            $session->instance->dictionary->cache->open($definition->schema, $definition->name);
        }
        $error = $session->settings()->legacy() ? 'Got error -2 from storage engine' : (new Tablespaces())->message($definition->schema . '/' . $definition->name, $session->settings()->release());
        $session->diagnostics->error($session->settings()->legacy() ? 1030 : SchemaError::TablespaceDiscarded->value, $error);
        return [['note', 'Table does not support optimize, doing recreate + analyze instead'], ['error', $error], ['status', 'Operation failed']];
    }
}
