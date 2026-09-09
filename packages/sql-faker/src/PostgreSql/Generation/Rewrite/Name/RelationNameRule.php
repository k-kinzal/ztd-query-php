<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y/makeRangeVarFromQualifiedName accepts at most three String components, without star or subscripts.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class RelationNameRule implements RewriteRule
{
    /**
     * Removes invalid relation-name indirections while preserving ordinary column and expression indirections.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $components = [];
        foreach (array_reverse($sequence->occurrences('indirection_el')) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $parent = count($origin->rules) - 2;
            while (($origin->rules[$parent] ?? null) === 'indirection') {
                --$parent;
            }
            if (!in_array($origin->rules[$parent] ?? null, ['qualified_name', 'PublicationObjSpec'], true)) {
                continue;
            }
            $scope = $origin->ancestors[$parent];
            $count = $components[$scope] ?? 0;
            if ($origin->name === '[' || $sequence->nameAt($range[0] + 1) === '*' || $count >= 2) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'postgresql.relation-name');
            } else {
                $components[$scope] = $count + 1;
            }
        }
        return $sequence;
    }
}
