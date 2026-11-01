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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        throw $this->error($operation);
    }

    /**
     * Answers the same rejection before unrelated binding diagnostics can win.
     */
    public function error(MySqlStatement $operation): SqlError
    {
        $text = $operation->toString();
        $near = mb_strcut(substr($text, 5), 0, 80, 'UTF-8');

        return new SqlError(StatementError::ParseError, StatementError::ParseError->message($near, 1));
    }
}
