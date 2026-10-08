<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
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
 * The patterns are read in order. A pattern outside the secure directory is refused by
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ImportTable);
        $file = $statement->files[0]->value;
        if (str_starts_with($file, LoadDataCommand::SECURE_DIRECTORY)) {
            throw ErrorCode::NoSdiFiles->error(substr($file, strlen(LoadDataCommand::SECURE_DIRECTORY)));
        }

        throw ErrorCode::OptionPreventsStatement->error("--secure-file-priv='" . LoadDataCommand::SECURE_DIRECTORY . "'");
    }
}
