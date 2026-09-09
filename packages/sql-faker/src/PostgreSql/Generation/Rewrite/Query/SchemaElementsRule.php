<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Query;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * CREATE SCHEMA with IF NOT EXISTS cannot contain schema elements.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L1517-L1586
 */
final class SchemaElementsRule implements RewriteRule
{
    /**
     * Removes the conditional modifier when elements exist, retaining their generated coverage.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('CreateSchemaStmt') as $owner) {
            $elements = $sequence->child($owner, 'OptSchemaEltList');
            $range = $sequence->range($owner);
            if ($range === null || $elements === null || $sequence->range($elements->id) === null) {
                continue;
            }
            if (array_slice($sequence->names(), $range[0] + 2, 3) === ['IF_P', 'NOT', 'EXISTS']) {
                $sequence = $sequence->replace($range[0] + 2, 3, [], 'gram.y:CreateSchemaStmt:conditional-elements');
            }
        }
        return $sequence;
    }
}
