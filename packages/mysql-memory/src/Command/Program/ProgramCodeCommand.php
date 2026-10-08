<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PROCEDURE CODE and SHOW FUNCTION CODE, which a server built without debugging support refuses.
 *
 * The statements are available only in debugging builds (ER_FEATURE_DISABLED), whatever routine
 * they name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-procedure-code.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramCodeCommand implements Command
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
     * Refuses the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        throw ErrorCode::FeatureDisabled->error('SHOW PROCEDURE|FUNCTION CODE', '--with-debug');
    }
}
