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
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Executes LOAD DATA and LOAD XML on a server that reads no files: it refuses each request as the server does.
 *
 * The server runs with --secure-file-priv=/var/lib/mysql-files/ and local loading disabled.
 * Several files and a URL need the bulk algorithm, which this server does not support, and
 * local loading is refused; these are checked while the statement is read. Then the table and
 * its columns must exist, and a PARTITION clause needs a partitioned table. A file outside the
 * secure directory is refused, and the secure directory holds no files. Every rule was verified
 * on a live 8.4 server. An ENCLOSED BY or ESCAPED BY of more than one byte is refused next,
 * before the table is opened (verified on live 8.0.44, 8.4.7 and 9.1.0 servers). MySQL 5.6 and
 * 5.7 refuse it while they read the statement, before anything else, and load local files: they check the table and its
 * columns first, then ask the client for the file, which the emulator cannot, so it refuses the
 * load then (verified on live 5.6.51 and 5.7.44 servers).
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
     * Refuses the load.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof LoadTable);
        $legacy = $session->settings()->legacy();
        if ($legacy) {
            $this->separators($statement);
        }
        $input = $statement->input;
        $this->algorithm($statement);
        if ($input->local && !$legacy) {
            throw StatementError::LocalInfileDisabled->error();
        }
        if (!$legacy) {
            $this->separators($statement);
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            $schema = $diagnostic instanceof MissingTable ? ($diagnostic->name->schema->value ?? $session->variables->database) : '';
            if ($schema !== '' && $session->instance->dictionary->schema($schema) === null) {
                throw QueryError::BadDatabase->error($schema);
            }
            if (Stages::answered($statement, $diagnostic)) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        if ($input->local) {
            throw StatementError::LocalInfileDisabled->error();
        }
        if (str_starts_with($input->file->value, self::SECURE_DIRECTORY)) {
            throw AdministrationError::FileStat->error($input->file->value, 2, 'No such file or directory');
        }

        throw StatementError::OptionPreventsStatement->error('--secure-file-priv');
    }

    /**
     * Checks input options requiring bulk loading before opening the target table.
     *
     * @throws \MySqlMemory\Error\SqlError When options require the bulk algorithm or bulk loading is requested
     */
    public function algorithm(LoadTable $statement): void
    {
        $bulk = $statement->bulk !== null && $statement->bulk->bulk;
        if ($statement->input->count !== null && !$bulk) {
            throw StatementError::WrongUsage->error('LOAD DATA without BULK Algorithm', 'multiple files');
        }
        if ($statement->input->source === LoadSource::Url && !$bulk) {
            throw StatementError::WrongUsage->error('LOAD DATA without BULK Algorithm', 'URL source');
        }
        if ($statement->compression !== null && !$bulk) {
            throw StatementError::WrongUsage->error('LOAD DATA without BULK Algorithm', 'COMPRESSION specified!');
        }
        if ($bulk) {
            throw StatementError::NotSupportedYet->error('Bulk Load');
        }
    }

    /**
     * Refuses an ENCLOSED BY or ESCAPED BY of more than one byte (ER_WRONG_FIELD_TERMINATORS).
     *
     * @throws \MySqlMemory\Error\SqlError When a separator that encloses or escapes is longer than one byte
     */
    public function separators(LoadTable $statement): void
    {
        foreach ($statement->fields as $option) {
            $digits = strlen($option->text->value);
            $bytes = match ($option->text->radix) {
                Radix::Hexadecimal => intdiv($digits + 1, 2),
                Radix::Bit => intdiv($digits + 7, 8),
                null => $digits,
            };
            if ($option->kind !== FieldOptionKind::Terminated && $bytes > 1) {
                throw StatementError::WrongFieldTerminators->error();
            }
        }
    }
}
