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
    public function completed(Node $node, Lowering $lowering, ?Token $preceding = null): ?Deprecation
    {
        $first = $node->children[0] ?? null;
        if (!Deprecated::ReplicationSpelling->warnedIn($lowering->profile->grammar)) {
            return null;
        }
        if ($node->name === 'opt_replica_reset_options' && $preceding?->name === 'SLAVE') {
            return new Deprecation(Deprecated::ReplicationSpelling, "'RESET SLAVE' is deprecated and will be removed in a future release. Please use RESET REPLICA instead");
        }
        if (!$first instanceof Token) {
            return null;
        }
        if ($node->name === 'change_replication_source' && $first->name === 'MASTER_SYM') {
            $old = 'CHANGE MASTER';
            $replacement = 'CHANGE REPLICATION SOURCE';
        } elseif ($node->name === 'replica' && $first->name === 'SLAVE' && $preceding?->name === 'START_SYM') {
            $old = 'START SLAVE';
            $replacement = 'START REPLICA';
        } elseif ((str_starts_with($node->name, 'change_replication_source_') || in_array($node->name, ['source_log_file', 'source_log_pos'], true)) && (str_starts_with($first->name, 'MASTER_') || $first->name === 'GET_MASTER_PUBLIC_KEY_SYM')) {
            $kind = (new SourceOptionRule($lowering))->keyword($node);
            $old = $kind->warningName(Terminology::Legacy);
            $replacement = $kind->warningName(Terminology::Current);
        } else {
            return null;
        }

        return new Deprecation(Deprecated::ReplicationSpelling, "'" . $old . "' is deprecated and will be removed in a future release. Please use " . $replacement . ' instead');
    }

    /**
     * Reads the preceding accepted keyword while the syntax tree is still transient.
     */
    public function preceding(Node $node, \SqlParser\Parser\SqlParser $parser, string $sql, ?Token $lookahead = null): ?Token
    {
        if (!in_array($node->name, ['replica', 'opt_replica_reset_options'], true)) {
            return null;
        }
        $range = (new \SqlSemantics\Construction\SourceRanges())->span($node, true);
        $offset = $range[0] ?? $lookahead?->offset;
        $preceding = null;
        foreach ($offset === null ? [] : $parser->tokenize(substr($sql, 0, $offset)) as $token) {
            if ($token->text !== '') {
                $preceding = $token;
            }
        }

        return $preceding;
    }
}
