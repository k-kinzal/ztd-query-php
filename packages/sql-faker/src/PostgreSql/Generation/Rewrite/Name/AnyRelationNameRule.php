<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y/makeRangeVarFromAnyName limits composite type relation names to three components.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class AnyRelationNameRule implements RewriteRule
{
    /**
     * Limits only the three grammar actions constructing a RangeVar from any_name.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('any_name') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $position = array_search('any_name', $origin->rules, true);
            if ($position === false || $position === 0) {
                continue;
            }
            $parent = $origin->ancestors[$position - 1];
            $rule = $origin->rules[$position - 1];
            if ($rule !== 'AlterCompositeTypeStmt'
                && !($rule === 'DefineStmt' && $sequence->child($parent, 'OptTableFuncElementList') !== null)
                && !($rule === 'RenameStmt' && $sequence->nameAt($range[1]) === 'RENAME' && $sequence->nameAt($range[1] + 1) === 'ATTRIBUTE')) {
                continue;
            }
            $count = 0;
            foreach (array_reverse($sequence->occurrences('attr_name')) as $attribute) {
                $part = $sequence->range($attribute);
                if ($part === null || $sequence->terminals[$part[0]]->ancestor('any_name') !== $id) {
                    continue;
                }
                if (++$count > 2) {
                    $sequence = $sequence->replace($part[0] - 1, $part[1] - $part[0] + 1, [], 'gram.y:makeRangeVarFromAnyName');
                }
            }
        }
        return $sequence;
    }
}
