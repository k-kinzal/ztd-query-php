<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Keeps grammar notices before the complete role definition's invocation condition.
 * Original input is confined to this boundary.
 *
 * @visibility MySqlMemory
 */
final class RoleCreationInput
{
    /**
     * Executes a retained definition, or reports the unsupported prepared command.
     *
     * @throws CompletedTemplate When execution returns a complete reply
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        Check::invariant($problem instanceof RefusedCreateRole, 'The refusal retains its complete role definition.');
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        $error = (new ModernWarnings())->recorded(array_column($notices, 1), StatementError::UnsupportedPreparedStatement->error(), $session, false);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
            return $error;
        }
        try {
            if ($session->preparation->inputPrepared) {
                throw StatementError::UnsupportedPreparedStatement->error();
            }
            throw new CompletedTemplate((new \MySqlMemory\Program\Account\RoleCreationUse())->run($problem->request, $session, false));
        } catch (SqlError $error) {
            $session->diagnostics->error($error->getCode(), $error->getMessage());
            return new SqlError($error->error, $error->getMessage(), $failure, recorded: true);
        }
    }
}
