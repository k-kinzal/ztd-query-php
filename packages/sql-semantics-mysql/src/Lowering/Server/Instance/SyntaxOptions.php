<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Server\DatabaseOptions;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves database-option failures before a later input refusal.
 *
 * Each completed list uses the same typed option rules as full derivation.
 * Only the newest option's problem is returned; earlier reductions own their
 * conditions. No input text or parser node is retained by the notice.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxOptions
{
    /**
     * Answers a failure at the newly completed option of a database option list.
     */
    public function completed(Node $node, Lowering $lowering): ParseFailure|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|null
    {
        if ($node->name === 'default_collation') {
            $option = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation($lowering->charsets->collation($node) ?? new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null));

            return (new DatabaseOptions())->collationNotice($option, $lowering->profile->grammar);
        }
        if (!in_array($node->name, ['create_database_options', 'alter_database_options'], true)) {
            return null;
        }
        try {
            $options = (new DatabaseRule($lowering))->options($node);
        } catch (AnalysisException) {
            // The input boundary reports a refused operand before this list can complete.
            return null;
        }
        $problems = (new DatabaseOptions())->problems($options, $lowering->profile->grammar);
        $problem = $problems[count($options) - 1] ?? null;

        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
