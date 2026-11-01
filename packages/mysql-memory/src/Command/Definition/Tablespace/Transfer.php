<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Tablespace;

use LogicException;
use MySqlMemory\Command\Definition\Database;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Partitions;
use MySqlMemory\Storage\References;
use MySqlMemory\Storage\Tablespaces;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;

/**
 * Detaches InnoDB table files and reports unavailable import files.
 *
 * The definition survives DISCARD, including on temporary tables in MySQL 5.6.
 * Later releases refuse temporary transfers. IMPORT cannot recover a discarded
 * file without an externally supplied .ibd file. A failed import leaves the
 * detached state intact. Verified on MySQL 5.6.51, 5.7.44 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-table-import.html.
 *
 * @visibility MySqlMemory
 */
final class Transfer
{
    /**
     * Applies a complete transfer request after the table has been resolved.
     *
     * @param list<AlterCommand> $commands The owning ALTER's actions and modifiers
     * @throws SqlError When the engine, modifiers, references or import files prevent transfer
     */
    public function execute(TablespaceCommand $command, StoredTable $table, array $commands, Session $session, Context $context): Completion
    {
        $this->modifiers($commands, $session->settings()->legacy());
        $definition = $table->definition;
        if (strcasecmp($definition->engine, 'InnoDB') !== 0 || ($context->modes->release === GrammarRelease::MySql5651 && $definition->partitioning !== null)) {
            throw SchemaError::IllegalHa->error($definition->name);
        }
        if ($definition->temporary && $context->modes->release !== GrammarRelease::MySql5651) {
            throw new SqlError(SchemaError::TemporaryTablespace, SchemaError::TemporaryTablespace->message(), following: [[1459, 'Table upgrade required. Please do "REPAIR TABLE `' . $definition->name . '`" or dump/reload to fix it!']]);
        }
        $partitions = $this->partitions($command, $table, $context);
        if ($command->import) {
            $this->import($table, $partitions[0], $session, $context);
        }
        $references = new References($session, $context);
        if ($references->enabled() && $references->children($table) !== []) {
            throw ConstraintError::RowIsReferenced->error('');
        }
        $storage = new Tablespaces();
        foreach ($partitions as $partition) {
            if ($storage->missing($table, $partition === null ? null : [$partition])) {
                $message = $context->modes->release === GrammarRelease::MySql5651 ? "InnoDB: Tablespace is missing for table '" . $definition->name . "'" : 'InnoDB: Tablespace is missing for table ' . $this->path($table, $partition, $session) . '.';
                $session->diagnostics->warning(SchemaError::TablespaceUnavailable, $message);
            }
        }
        $storage->discard($table, $partitions, $context);
        return new Completion(warnings: $session->diagnostics->raised());
    }

    /**
     * Requires DEFAULT algorithms and lock levels for file transfers.
     *
     * @param list<AlterCommand> $commands The owning ALTER's actions and modifiers
     * @throws SqlError When an explicit algorithm or lock level is unsupported
     */
    public function modifiers(array $commands, bool $legacy): void
    {
        foreach ($commands as $command) {
            if ($command instanceof AlgorithmOption && !in_array(strtoupper($command->algorithm->value ?? 'DEFAULT'), ['DEFAULT'], true)) {
                throw SchemaError::AlterOperationNotSupported->error($legacy ? 'ALGORITHM=COPY/INPLACE' : 'ALGORITHM=COPY/INPLACE/INSTANT', 'ALGORITHM=DEFAULT');
            }
            if ($command instanceof LockOption && !in_array(strtoupper($command->lock->value ?? 'DEFAULT'), ['DEFAULT'], true)) {
                throw SchemaError::AlterOperationNotSupported->error('LOCK=NONE/SHARED/EXCLUSIVE', 'LOCK=DEFAULT');
            }
        }
    }

    /**
     * Resolves named partitions before detaching any of them.
     *
     * @return non-empty-list<int|null> File positions, or null for an unpartitioned table
     * @throws SqlError When partition selection is invalid
     * @throws LogicException When a stored partition definition has no physical partition
     */
    public function partitions(TablespaceCommand $command, StoredTable $table, Context $context): array
    {
        $partitioning = $table->definition->partitioning;
        if ($partitioning === null) {
            if ($command->partitions !== null) {
                throw SchemaError::PartitionManagementOnNonpartitioned->error();
            }
            return [null];
        }
        $positions = $command->partitions instanceof NamedPartitions
            ? (new Partitions($table->definition, $partitioning, $context))->selected($command->partitions->names)
            : array_keys($partitioning->partitions);
        if ($positions === []) {
            throw new LogicException('A partitioned table has at least one physical partition.');
        }
        return $positions;
    }

    /**
     * Reports an existing file or the missing exported file without changing state.
     *
     * @throws SqlError Every import requires an external file unavailable to this instance
     */
    public function import(StoredTable $table, ?int $partition, Session $session, Context $context): never
    {
        $definition = $table->definition;
        $oldest = $context->modes->release === GrammarRelease::MySql5651;
        if (!(new Tablespaces())->missing($table, $partition === null ? null : [$partition])) {
            $message = $oldest ? "Tablespace for table '" . $definition->name . "' exists. Please DISCARD the tablespace before IMPORT." : SchemaError::TablespaceExists->message($this->path($table, $partition, $session));
            $following = $session->settings()->legacy() ? 'Got error 156 from storage engine' : "Got error 156 - 'The table already existed in storage engine' from storage engine";
            throw new SqlError(SchemaError::TablespaceExists, $message, following: [[1030, $following]]);
        }
        $path = $this->path($table, $partition, $session);
        $session->diagnostics->warning(1810, "InnoDB: IO Read error: (2, No such file or directory) Error opening './" . $path . ".cfg', will attempt to import without schema verification");
        $name = $oldest ? "'\"" . $definition->schema . '"."' . $definition->name . "\"'" : '`' . $definition->schema . '`.`' . $definition->name . '`';
        $suffix = $partition === null ? '' : ' /* Partition `' . ($definition->partitioning?->partitions[$partition]->name ?? '') . '` */';
        $session->diagnostics->warning(1816, 'InnoDB: ALTER TABLE ' . $name . $suffix . " IMPORT TABLESPACE failed with error 44 : 'Tablespace not found'");
        throw $oldest ? QueryError::NoSuchTable->error($definition->schema, $definition->name) : SchemaError::TablespaceUnavailable->error($name);
    }

    /**
     * Answers the engine's encoded database/file name, including partition spelling.
     */
    public function path(StoredTable $table, ?int $partition, Session $session): string
    {
        $file = new Database\FileName();
        $definition = $table->definition;
        $suffix = $partition === null ? '' : ($session->settings()->legacy() ? '#P#' : '#p#') . $file->encoded($definition->partitioning?->partitions[$partition]->name ?? '');
        return $file->encoded($definition->schema) . '/' . $file->encoded($definition->name) . $suffix;
    }
}
