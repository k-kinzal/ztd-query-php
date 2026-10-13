<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * Preserves replication keyword warnings when a later operand is rejected.
 *
 * CHANGE MASTER and each MASTER option warn at their keyword reduction,
 * before the option value. Observed on an isolated MySQL 8.0.44 server.
 * The returned warning retains decoded vocabulary only, without SQL or AST.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDeprecations
{
    /**
     * Answers a deprecation for a completed legacy replication keyword.
     */
    public function completed(Node $node, Lowering $lowering): ?Deprecation
    {
        $first = $node->children[0] ?? null;
        if (!Deprecated::ReplicationSpelling->warnedIn($lowering->profile->grammar) || !$first instanceof Token) {
            return null;
        }
        if ($node->name === 'change_replication_source' && $first->name === 'MASTER_SYM') {
            $old = 'CHANGE MASTER';
            $replacement = 'CHANGE REPLICATION SOURCE';
        } elseif ((str_starts_with($node->name, 'change_replication_source_') || in_array($node->name, ['source_log_file', 'source_log_pos'], true)) && (str_starts_with($first->name, 'MASTER_') || $first->name === 'GET_MASTER_PUBLIC_KEY_SYM')) {
            $kind = (new SourceOptionRule($lowering))->keyword($node);
            $old = $kind->keyword(Terminology::Legacy);
            $replacement = $kind->value;
        } else {
            return null;
        }

        return new Deprecation(Deprecated::ReplicationSpelling, "'" . $old . "' is deprecated and will be removed in a future release. Please use " . $replacement . ' instead');
    }
}
