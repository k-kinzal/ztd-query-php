<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml\Insert;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Retains DELAYED notices when a later INSERT or REPLACE operand cannot be parsed.
 *
 * The accepted lock-option production identifies the command independently of comments
 * or later syntax. Only the release-specific notice survives lowering.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert-delayed.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDeprecations
{
    /**
     * Answers the warning of a completed DELAYED option, before reading its target or values.
     */
    public function completed(Node $node, GrammarRelease $release): ?Deprecated
    {
        $first = $node->children[0] ?? null;
        if (!$first instanceof Token || $first->name !== 'DELAYED_SYM') {
            return null;
        }

        return match ($node->name) {
            'insert_lock_option' => $release === GrammarRelease::MySql5651 ? Deprecated::DelayedInsert : Deprecated::InsertDelayed,
            'replace_lock_option' => $release === GrammarRelease::MySql5651 ? Deprecated::DelayedReplace : Deprecated::ReplaceDelayed,
            default => null,
        };
    }
}
