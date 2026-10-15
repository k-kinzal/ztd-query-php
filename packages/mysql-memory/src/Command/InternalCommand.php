<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Statement;

/**
 * Refuses debug-only structures on the emulated release build.
 *
 * Direct construction has no original SQL text. Its diagnostic quotes the
 * canonical rendering of the semantic structure; rendering only supplies
 * observable error text and is never parsed to choose behavior. The SQL input
 * path retains its earlier rejection and quotes the client's exact spelling.
 * Verified on a MySQL 8.4.7 release build.
 *
 * @visibility MySqlMemory
 */
final class InternalCommand implements Command
{
    /**
     * @var list<class-string<Statement>> The structures rejected before their operands are evaluated
     */
    public const STATEMENTS = [ShowParseTree::class];

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Reports the client protocol's rejection of the structure.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        throw $this->error($operation);
    }

    /**
     * Checks account operands read before a release build refuses SHOW PARSE_TREE.
     * These checks consume typed account names without resolving or executing the wrapped command.
     *
     * @throws SqlError When a wrapped account name is too long or malformed
     */
    public function operands(Operation $operation): void
    {
        if ($operation->statement instanceof ShowParseTree) {
            (new Account\Names($operation->profile()->grammar))->check((new \MySqlMemory\Evaluation\Compile\Walker())->find($operation->statement->statement, \SqlSemantics\Platform\MySql\Statement\Name\AccountName::class));
        }
    }

    /**
     * Answers the same rejection before unrelated binding diagnostics can win.
     */
    public function error(Operation $operation): SqlError
    {
        $this->operands($operation);
        $text = $operation->toString();
        $near = mb_strcut(substr($text, 5), 0, 80, 'UTF-8');

        return new SqlError(StatementError::ParseError, StatementError::ParseError->message($near, 1));
    }
}
