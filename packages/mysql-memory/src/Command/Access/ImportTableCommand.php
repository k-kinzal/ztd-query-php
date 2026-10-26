<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes IMPORT TABLE on a server that reads no files: it refuses each request as the server does.
 *
 * The current database is required before file access. The patterns are read in order.
 * A pattern outside the secure directory is refused by
 * --secure-file-priv, and a pattern inside it matches no serialized dictionary file, since the
 * directory holds none (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/import-table.html.
 *
 * @visibility MySqlMemory
 */
final class ImportTableCommand implements Command
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
     * Refuses the import.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        assert($statement instanceof ImportTable);
        if ($session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }
        $file = $statement->files[0]->value;
        if (str_starts_with($file, LoadDataCommand::SECURE_DIRECTORY)) {
            throw SchemaError::NoSdiFiles->error(substr($file, strlen(LoadDataCommand::SECURE_DIRECTORY)));
        }

        throw StatementError::OptionPreventsStatement->error("--secure-file-priv='" . LoadDataCommand::SECURE_DIRECTORY . "'");
    }
}
