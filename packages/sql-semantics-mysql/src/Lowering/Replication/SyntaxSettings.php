<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;

/**
 * Preserves completed binary log index and line-feed checks before later input errors.
 *
 * The notice retains the decoded operand needed to format its error. No SQL,
 * parser node or execution state survives the lowering call.
 * Observed through isolated MySQL 8.0, 8.4 and 9.1 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxSettings
{
    /**
     * Returns the aborting condition of a completed index or newline-free text operand.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'source_def') {
            try {
                (new SourceOptionRule($lowering))->option($node, (new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($lowering->profile->grammar));
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                return new ParseFailure($failure->problem, $failure->aborts);
            }
        }
        if ($node->name === 'TEXT_STRING_sys_nonewline') {
            $text = $lowering->literals->text($node);

            return str_contains($text->value, "\n") ? new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting(\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::LineFeed, text: $text), true) : null;
        }
        if ($node->name !== 'source_reset_options') {
            return null;
        }
        $number = (new ResetRule($lowering))->first($node);
        $problem = ResetBinaryLogs::problem($number);

        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
