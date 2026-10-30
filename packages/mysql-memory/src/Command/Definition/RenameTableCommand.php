<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Executes RENAME TABLE: renames tables one after the other, all or none.
 *
 * Source and destination names are checked in written order, while the statement is read: an empty name, or one that ends
 * with a space, is ER_WRONG_TABLE_NAME, and a name longer than 64 characters
 * ER_TOO_LONG_IDENT. Then each rename in order: the databases must exist, the new name must be
 * free, and the table must exist; a temporary table is not renamed by this statement. A later
 * rename sees the names the earlier ones produced. The statement commits the open transaction.
 * Every rule was verified on a live 8.4 server. MySQL 5.6 and 5.7 look for the file of the table
 * first and report a missing table as a missing .frm file (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html.
 *
 * @visibility MySqlMemory
 */
final class RenameTableCommand implements Command
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
     * Renames the tables.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof RenameTable);
        $database = $session->variables->database;
        $this->written($statement, $database);
        $locks = $this->locks($statement, $session);
        $session->transaction->commit();
        $dictionary = $session->instance->dictionary;
        [$names, $moves] = $this->prepare($statement, $dictionary, $database, $session->instance->version);
        foreach ($moves as [$table]) {
            if ($table instanceof View) {
                unset($dictionary->schemas[$table->schema]->views[$table->name]);
                continue;
            }
            unset($dictionary->schemas[$table->definition->schema]->tables[$table->definition->name]);
            $dictionary->cache->close($table->definition->schema, $table->definition->name);
        }
        foreach ($moves as [$table, $to]) {
            if ($table instanceof View) {
                continue;
            }
            $from = [$table->definition->schema, $table->definition->name];
            $table->definition = $this->renamed($session, $context, $connection, $table, $to[0], $to[1]);
            $dictionary->retarget($from[0], $from[1], $to[0], $to[1]);
        }
        foreach ($names as $table) {
            if ($table instanceof View) {
                $dictionary->schemas[$table->schema]->views[$table->name] = $table;
                continue;
            }
            $dictionary->schemas[$table->definition->schema]->tables[$table->definition->name] = $table;
        }
        $session->locks = $locks;

        return new Completion();
    }

    /**
     * Validates the whole ordered rename list before publishing any catalog change.
     *
     * @return array{array<string, StoredTable|View>, list<array{StoredTable|View, array{string, string}}>}
     * @throws \MySqlMemory\Error\SqlError When any source or destination cannot be renamed
     */
    public function prepare(RenameTable $statement, Dictionary $dictionary, string $database, string $version): array
    {
        $legacy = str_starts_with($version, '5.');
        $names = $this->names($dictionary);
        $moves = [];
        foreach ($statement->renamings as $renaming) {
            $from = [$renaming->from->schema->value ?? $database, $renaming->from->name->value];
            $to = [$renaming->to->schema->value ?? $database, $renaming->to->name->value];
            if ($legacy && isset($names[$to[0] . "\0" . $to[1]])) {
                throw SchemaError::TableExists->error($to[1]);
            }
            if ($legacy && !isset($names[$from[0] . "\0" . $from[1]])) {
                $files = new Database\FileName();
                throw SchemaError::FileNotFound->error('./' . $files->encoded($from[0]) . '/' . $files->encoded($from[1]) . '.frm', 2, 'No such file or directory');
            }
            if ($legacy && $dictionary->schema($to[0]) === null) {
                $files = new Database\FileName();
                $older = str_starts_with($version, '5.6.');
                throw SchemaError::RenameFailed->error('./' . $files->encoded($from[0]) . '/' . $files->encoded($from[1]), './' . $files->encoded($to[0]) . '/' . $files->encoded($to[1]), $older ? -1 : 168, $older ? 'Unknown error -1' : 'Unknown (generic) error from engine');
            }
            foreach ([$from[0], $to[0]] as $schema) {
                if ($dictionary->schema($schema) === null) {
                    throw QueryError::BadDatabase->error($schema);
                }
            }
            if (isset($names[$to[0] . "\0" . $to[1]])) {
                throw SchemaError::TableExists->error($to[1]);
            }
            $table = $names[$from[0] . "\0" . $from[1]] ?? null;
            if ($table === null) {
                throw QueryError::NoSuchTable->error($from[0], $from[1]);
            }
            if ($table instanceof View && $from[0] !== $to[0]) {
                throw SchemaError::ViewRenameSchema->error($from[0], $to[0]);
            }
            unset($names[$from[0] . "\0" . $from[1]]);
            $names[$to[0] . "\0" . $to[1]] = $table instanceof View ? $table->renamed($to[1]) : $table;
            $moves[] = [$table, $to];
        }
        return [$names, $moves];
    }

    /**
     * Checks every original source's metadata lock before any rename is attempted.
     * Earlier destinations may become later sources. The returned lock list is
     * published only after success; explicit aliases and lock modes survive renaming.
     * MySQL 5.x refuses RENAME TABLE while explicit table locks are held.
     *
     * @return list<\MySqlMemory\Session\State\TableLock>
     * @throws \MySqlMemory\Error\SqlError When a source lacks the required WRITE lock
     */
    public function locks(RenameTable $statement, Session $session): array
    {
        if ($session->locks === []) {
            return [];
        }
        if ($session->settings()->legacy()) {
            throw \MySqlMemory\Error\Family\StatementError::LockedOrActiveTransaction->error();
        }
        $created = [];
        $locks = $session->locks;
        foreach ($statement->renamings as $renaming) {
            $from = [$renaming->from->schema->value ?? $session->variables->database, $renaming->from->name->value];
            $to = [$renaming->to->schema->value ?? $session->variables->database, $renaming->to->name->value];
            $key = $from[0] . "\0" . $from[1];
            if (!isset($created[$key])) {
                (new \MySqlMemory\Command\Access\Locks())->metadata($session, $from[0], $from[1]);
            }
            unset($created[$key]);
            $created[$to[0] . "\0" . $to[1]] = true;
            foreach ($locks as $index => $lock) {
                if (!$lock->temporary && $lock->schema === $from[0] && $lock->table === $from[1]) {
                    $locks[$index] = $lock->renamed($to[0], $to[1]);
                }
            }
        }
        return $locks;
    }

    /**
     * Answers the tables a rename can see, keyed by their database and name joined by a NUL
     * character; temporary tables are left out.
     *
     * @return array<string, StoredTable|View>
     */
    public function names(Dictionary $dictionary): array
    {
        $names = [];
        foreach ($dictionary->schemas as $schema) {
            foreach ($schema->views as $name => $view) {
                $names[$schema->name . "\0" . $name] = $view;
            }
            foreach ($schema->tables as $name => $table) {
                if (!$table->definition->temporary) {
                    $names[$schema->name . "\0" . $name] = $table;
                }
            }
        }

        return $names;
    }

    /**
     * Refuses what the server refuses while it reads the statement: first a name no table
     * can have, then an unqualified name when no database is selected.
     *
     * @throws \MySqlMemory\Error\SqlError When a name is invalid or a name needs a database
     */
    public function written(RenameTable $statement, string $database): void
    {
        foreach ($statement->renamings as $renaming) {
            $this->valid($renaming->from->name->value);
            $this->valid($renaming->to->name->value);
        }
        foreach ($statement->renamings as $renaming) {
            if (($renaming->from->schema === null || $renaming->to->schema === null) && $database === '') {
                throw QueryError::NoDatabase->error();
            }
        }
    }

    /**
     * Refuses a name no table can have.
     *
     * @throws \MySqlMemory\Error\SqlError When the name is empty, ends with a space or is too long
     */
    public function valid(string $name): void
    {
        if ($name === '' || str_ends_with($name, ' ')) {
            throw SchemaError::WrongTableName->error($name);
        }
        if (mb_strlen($name) > 64) {
            throw SchemaError::TooLongIdentifier->error($name);
        }
    }

    /**
     * Refuses a new name whose database does not exist or that a table has.
     *
     * @throws \MySqlMemory\Error\SqlError When the name cannot be taken
     */
    public function vacant(Session $session, string $schema, string $name): void
    {
        $this->valid($name);
        if ($session->instance->dictionary->schema($schema) === null) {
            throw QueryError::BadDatabase->error($schema);
        }
        if ($session->instance->dictionary->table($schema, $name, $session->temporaries) !== null || isset($session->instance->dictionary->schema($schema)?->views[$name])) {
            throw SchemaError::TableExists->error($name);
        }
    }

    /**
     * Answers the definition of a table under another name, declared again so that statements bind to it by its new name.
     *
     * @throws \MySqlMemory\Error\SqlError When the definition cannot be declared again
     */
    public function renamed(Session $session, Context $context, Connection $connection, StoredTable $table, string $schema, string $name): TableDefinition
    {
        $layout = TableLayout::of($table->definition);
        $layout->name = new QualifiedName(new Name($name), new Name($schema));
        $others = array_values(array_filter(\MySqlMemory\Session\Statement\Catalog::declarations($session), static fn ($declaration): bool => $declaration !== $table->definition->declaration));

        return (new TableRebuild($session, $context, $connection))->definition($layout, $others, false)->withRowFormat($table->definition->rowFormat);
    }
}
