<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\DiagnosticLine;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Diagnostic\Encoding;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Formats a standalone partitioning refusal at the SQL input boundary.
 *
 * The original spelling supplies diagnostic text only. Neither a statement
 * nor an execution plan is built for this refused input. Native 5.6.51 and
 * 5.7.44 quote the remaining client text, including subsequent statements.
 *
 * @visibility MySqlMemory
 */
final class PartitionInput
{
    /**
     * Keeps completed prefix conditions before an entry with a malformed body.
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $error = $this->error($failure, $statement, $session);

        return (new \MySqlMemory\Session\CacheOptions())->condition($error, $statement, $session, $failure->end)
            ?? (new \MySqlMemory\Session\Parse\Reader())->rejection($failure, $statement, $session);
    }

    /**
     * Answers the client error at the refused PARTITION keyword.
     */
    public function error(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $offset = $failure->end - strlen('PARTITION');
        $line = (new DiagnosticLine())->at($statement, $offset, true, $session->semantics()->parser());
        $near = Encoding::prefix(rtrim(substr($statement, $offset) . $session->following, " \t\n\r\f\v"), Charset::named((string) $session->variables->read('character_set_client')) ?? Charset::known('utf8mb4'), true);

        return new SqlError(StatementError::ParseError, "Partitioning can not be used stand-alone in query near '" . $near . "' at line " . $line, $failure);
    }
}
