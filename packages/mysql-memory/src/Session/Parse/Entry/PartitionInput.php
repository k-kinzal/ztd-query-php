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
     * Formats the bound condition of a complete semantic partition definition.
     * The source location supplies only the quoted diagnostic suffix.
     */
    public function bound(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue $problem, ?\SqlSemantics\Statement\Source\Span $boundary, Session $session): SqlError
    {
        if (in_array($problem, [\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::RangeValues, \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ListValues], true)) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::WrongValuesKind, $problem->message());
        }
        if ($session->settings()->legacy()) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::MissingValues, $problem->message());
        }
        if ($boundary === null || $session->text === '') {
            return new SqlError(StatementError::ParseError, $problem->message());
        }
        $offset = $boundary->offset;
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($session->semantics()->parser(), $session->text) as $token) {
            if ($token->offset >= $offset) {
                $offset = $token->offset;
                break;
            }
        }
        $line = (new DiagnosticLine())->at($session->text, $offset, true, $session->semantics()->parser());
        $near = Encoding::prefix(rtrim(substr($session->text, $offset) . $session->following, " \t\n\r\f\v"), $session->resolution()->client ?? Charset::known('utf8mb4'), true);
        return new SqlError(StatementError::ParseError, $problem->message() . " near '" . $near . "' at line " . $line);
    }

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
    /**
     * Formats an intrinsic definition refusal after its completed table-name prefix.
     * An omitted subpartition list is refused at the preceding definition's end,
     * preserving subsequent whitespace and comments in the quoted suffix on modern
     * releases. Legacy releases read the following delimiter before refusal.
     */
    public function definition(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        if (!$session->settings()->legacy() && in_array($problem, [\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ZeroSubpartitions, \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ZeroPartitions], true)) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::NoPartitions, $problem->message(), $failure);
        }
        $database = (new \MySqlMemory\Session\Parse\Definition\DefinitionPrefix())->database($statement, $failure->end, $session, 'TABLE_SYM');
        if ($database !== null) {
            return $database;
        }
        \SqlSemantics\Diagnostic\Check::invariant($problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue, 'A partition-definition refusal has a typed reason.');
        if ($problem === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ZeroSubpartitions || $problem === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ZeroPartitions) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::NoPartitions, $problem->message(), $failure);
        }
        if ($problem === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::RangeValues || $problem === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::ListValues) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::WrongValuesKind, $problem->message(), $failure);
        }
        if ($problem !== \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount && $session->settings()->legacy()) {
            return new SqlError(\MySqlMemory\Error\Family\PartitionError::MissingValues, $problem->message(), $failure);
        }
        $offset = $failure->end;
        if ($problem !== \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::SubpartitionCount || $session->settings()->legacy()) {
            foreach ((new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($session->semantics()->parser(), $statement) as $token) {
                if ($token->offset >= $offset) {
                    $offset = $token->offset;
                    if ($session->settings()->legacy() && substr($statement, $offset, 1) === ',') {
                        ++$offset;
                    }
                    break;
                }
            }
        }
        $line = (new DiagnosticLine())->at($statement, $offset, true, $session->semantics()->parser());
        $near = Encoding::prefix(rtrim(substr($statement, $offset) . $session->following, " \t\n\r\f\v"), Charset::named((string) $session->variables->read('character_set_client')) ?? Charset::known('utf8mb4'), true);
        return new SqlError(StatementError::ParseError, $problem->message() . " near '" . $near . "' at line " . $line, $failure);
    }

}
