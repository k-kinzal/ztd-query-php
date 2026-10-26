<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Checks LOAD DATA and LOAD XML against an empty server file store and client upload transport.
 *
 * The server runs with --secure-file-priv=/var/lib/mysql-files/; modern releases
 * default to disabled local loading. Enabled LOCAL DATA consumes client records.
 * Several files and a URL need the bulk algorithm, which this server does not support, and
 * disabled local loading is refused; these are checked while the statement is read. MySQL
 * 8.0 and 8.1 ignore the bulk annotations and follow ordinary file loading,
 * including table, column and secure-file checks (verified through Testcontainers). Then the table and
 * its columns must exist, and a PARTITION clause needs a partitioned table. A file outside the
 * secure directory is refused, and the secure directory holds no files. Every rule was verified
 * on a live 8.4 server. An ENCLOSED BY or ESCAPED BY of more than one byte is refused next,
 * before the table is opened (verified on live 8.0.44, 8.4.7 and 9.1.0 servers). MySQL 5.6 and
 * 5.7 refuse it while they read the statement, before anything else. They check a local load's
 * table and columns before requesting the client file. A wire client can refuse the request
 * itself, sending an empty upload; this exchange preserves its own driver error and leaves
 * the next command usable. Enabled uploads decode DATA or XML records before row writes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_secure_file_priv.
 *
 * @visibility MySqlMemory
 */
final class LoadDataCommand implements Command
{
    /**
     * The directory the server reads and writes files in.
     */
    public const SECURE_DIRECTORY = '/var/lib/mysql-files/';

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Applies file-store restrictions or receives an enabled client upload.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        assert($statement instanceof LoadTable);
        $input = $statement->input;
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if (Stages::answered($statement, $diagnostic)) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        if ($input->local) {
            if ($session->running->readFile === null) {
                throw StatementError::LocalInfileDisabled->error();
            }
            $contents = ($session->running->readFile)($input->file->value);
            if ($contents !== '') {
                return (new Load\ClientRows())->execute($operation, $session, $context, $connection, $contents);
            }
            return new \MySqlMemory\Result\Completion(0, 0, $context->diagnostics->raised(), 'Records: 0  Deleted: 0  Skipped: 0  Warnings: ' . $context->diagnostics->raised());
        }
        if (str_starts_with($input->file->value, self::SECURE_DIRECTORY)) {
            throw AdministrationError::FileStat->error($input->file->value, 2, 'No such file or directory');
        }

        throw StatementError::OptionPreventsStatement->error('--secure-file-priv');
    }

    /**
     * Checks load-entry restrictions before preparing SET expressions.
     *
     * Grammar conditions have already been reported. Modern disabled LOCAL
     * loading then precedes subquery resolution and scalar-width checks.
     *
     * @throws \MySqlMemory\Error\SqlError When the input is unavailable or incompatible
     */
    public function prepare(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        assert($statement instanceof LoadTable);
        $this->entry($statement, $operation->facts, $session);
    }

    /**
     * Checks the same complete LOAD header for ordinary and refused assignments.
     *
     * @throws \MySqlMemory\Error\SqlError When entry or relation conditions prevent preparation
     */
    public function entry(LoadTable|RefusedLoadAssignments $statement, Facts $facts, Session $session): void
    {
        $legacy = $session->settings()->legacy();
        if ($legacy) {
            $this->separators($statement);
        }
        if ($statement->table->name->schema === null && $session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }
        $input = $statement->input;
        $this->options($statement, $facts, $session);
        if ($input->local && !in_array($session->variables->read('local_infile'), ['ON', '1', 1], true)) {
            throw ($legacy ? StatementError::NotAllowedCommand : StatementError::LocalInfileDisabled)->error();
        }
        if (!$legacy) {
            $this->separators($statement);
        }
        if (preg_match('/[\x80-\xff]/', implode('', $this->separatorBytes($statement))) === 1) {
            $error = \MySqlMemory\Error\Family\DataError::NonAsciiSeparator;
            $session->diagnostics->warning($error, $error->message());
        }
        foreach ($facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition) {
                throw (new Errors())->error($diagnostic, $session);
            }
            if (!$diagnostic instanceof MissingTable) {
                continue;
            }
            $schema = $diagnostic->name->schema->value ?? $session->variables->database;
            if ($schema !== '' && $session->instance->dictionary->schema($schema) === null) {
                throw Errors::unknown($schema, $diagnostic->name->name->value, $session->settings()->release());
            }
            throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
        }
    }

    /**
     * Checks input options requiring bulk loading before opening the target table.
     *
     * @throws \MySqlMemory\Error\SqlError When options require the bulk algorithm or bulk loading is requested
     */
    public function algorithm(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        assert($statement instanceof LoadTable);
        $this->options($statement, $operation->facts, $session);
    }

    /**
     * Checks algorithm support independently of assignment operand roles.
     *
     * @throws \MySqlMemory\Error\SqlError When bulk input cannot be used
     */
    public function options(LoadTable|RefusedLoadAssignments $statement, Facts $facts, Session $session): void
    {
        if (version_compare($session->instance->version, '8.2.0', '<')) {
            return;
        }
        foreach ($facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof LoadConflict) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        if ($statement->bulk->bulk ?? false) {
            $error = StatementError::NotSupportedYet->error('Bulk Load');
            throw new \MySqlMemory\Error\SqlError($error->error, $error->getMessage(), following: $this->bulkConditions($statement, $session));
        }
    }

    /**
     * Retains the target/file conditions MySQL 8.2 and 8.3 append after refusing BULK.
     * Later releases stop at the unsupported algorithm. Observed through SQL and
     * SHOW WARNINGS with absent tables, tables without a primary key and keyed tables.
     *
     * @return list<array{int, string}> Additional error conditions, in native order
     */
    public function bulkConditions(LoadTable|RefusedLoadAssignments $statement, Session $session): array
    {
        if (!in_array($session->instance->version, ['8.2.0', '8.3.0'], true)) {
            return [];
        }
        $schema = $statement->table->name->schema->value ?? $session->variables->database;
        $name = $statement->table->name->name->value;
        $table = $session->instance->dictionary->table($schema, $name);
        if ($table === null) {
            return [[1146, QueryError::NoSuchTable->message($schema, $name)]];
        }
        if ($table->definition->primaryKey() === null) {
            return [[4130, "Table '" . $schema . '/' . $name . "' has no primary key."]];
        }
        $file = $statement->input->file->value;
        $error = str_starts_with($file, self::SECURE_DIRECTORY) ? AdministrationError::FileStat->error($file, 2, 'No such file or directory') : StatementError::OptionPreventsStatement->error('--secure-file-priv');

        return [[$error->getCode(), $error->getMessage()]];
    }

    /**
     * Refuses an ENCLOSED BY or ESCAPED BY of more than one byte (ER_WRONG_FIELD_TERMINATORS).
     *
     * @throws \MySqlMemory\Error\SqlError When a separator that encloses or escapes is longer than one byte
     */
    public function separators(LoadTable|RefusedLoadAssignments $statement): void
    {
        foreach ($this->separatorBytes($statement) as $kind => $bytes) {
            if (in_array($kind, ['field:ENCLOSED', 'field:ESCAPED'], true) && strlen($bytes) > 1) {
                throw StatementError::WrongFieldTerminators->error();
            }
        }
    }

    /**
     * Resolves repeated options to their last value before validating separators.
     *
     * @return array<string, string> Effective field and line separator bytes
     */
    public function separatorBytes(LoadTable|RefusedLoadAssignments $statement): array
    {
        $bytes = [];
        foreach ($statement->fields as $option) {
            $kind = $option->kind === FieldOptionKind::OptionallyEnclosed ? FieldOptionKind::Enclosed : $option->kind;
            $bytes['field:' . $kind->value] = $option->text->bytes();
        }
        foreach ($statement->lines as $option) {
            $bytes['line:' . $option->kind->value] = $option->text->bytes();
        }
        if ($statement->rowTag !== null) {
            $bytes['xml:ROW'] = $statement->rowTag->bytes();
        }

        return $bytes;
    }
}
