<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;

/**
 * Preserves completed binary log index validation before later syntax errors.
 *
 * The notice retains the numeric operand needed to format its error. No SQL,
 * parser node or execution state survives the lowering call.
 * Observed through isolated MySQL 8.0, 8.4 and 9.1 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxSettings
{
    /**
     * Returns the aborting condition of a completed RESET TO operand.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name !== 'source_reset_options') {
            return null;
        }
        $number = (new ResetRule($lowering))->first($node);
        $target = new ResetBinaryLogs((new Releases())->binaryLogs($lowering->profile->grammar), $number);
        $problem = $target->problem();

        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
