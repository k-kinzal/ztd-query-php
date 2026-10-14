<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Statement\Operation;

/**
 * Completes a comment-only query, whose semantic script has no statements.
 *
 * Whitespace-only packets are refused before dispatch. A comment-only packet clears
 * diagnostics in modern releases and retains them in 5.6, as other table-free statements do.
 * Verified through PDO on isolated MySQL 5.6.51 and 8.4.7 servers.
 *
 * @visibility MySqlMemory
 */
final class EmptyCommand implements Command
{
    /**
     * Requests the release-specific reset or retention of the diagnostics area.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Completes without changing data or session variables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        return new Completion(0, 0, $context->diagnostics->raised());
    }
}
