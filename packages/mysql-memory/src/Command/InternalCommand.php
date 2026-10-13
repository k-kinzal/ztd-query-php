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
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Statement;

/**
 * Refuses internal and debug-only structures at the ordinary client boundary.
 *
 * Direct construction has no original SQL text. Its diagnostic quotes the
 * canonical rendering of the semantic structure; rendering only supplies
 * observable error text and is never parsed to choose behavior. The SQL input
 * path retains its earlier rejection and quotes the client's exact spelling.
 * Verified on MySQL 5.7.44 and 8.4.7 release builds.
 *
 * @visibility MySqlMemory
 */
final class InternalCommand implements Command
{
    /**
     * @var list<class-string<Statement>> The structures rejected before their operands are evaluated
     */
    public const STATEMENTS = [PartitionEntry::class, ParseGeneratedColumn::class, ShowParseTree::class];

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
     * Answers the same rejection before unrelated binding diagnostics can win.
     */
    public function error(Operation $operation): SqlError
    {
        $statement = $operation->statement;
        assert($statement instanceof PartitionEntry || $statement instanceof ParseGeneratedColumn || $statement instanceof ShowParseTree);
        $text = $operation->toString();
        $near = mb_strcut($statement instanceof ShowParseTree ? substr($text, 5) : $text, 0, 80, 'UTF-8');

        return new SqlError(StatementError::ParseError, $statement instanceof PartitionEntry
            ? "Partitioning can not be used stand-alone in query near '" . $near . "' at line 1"
            : StatementError::ParseError->message($near, 1));
    }
}
