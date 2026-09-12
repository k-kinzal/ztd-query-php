<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * gram.y/ConstraintAttributeSpec rejects three conflicting pairs of accumulated option bits.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class ConstraintAttributesRule implements RewriteRule
{
    /**
     * Retains compatible and redundant options; the first incompatible later option is removed.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $seen = [];
        foreach (array_reverse($sequence->occurrences('ConstraintAttributeElem')) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $index = array_search('ConstraintAttributeSpec', $origin->rules, true);
            if ($index === false) {
                continue;
            }
            $scope = $origin->ancestors[$index];
            $names = implode(' ', array_slice($sequence->names(), $range[0], $range[1] - $range[0]));
            $bit = match ($names) {
                'NOT DEFERRABLE' => 1, 'DEFERRABLE' => 2, 'INITIALLY IMMEDIATE' => 4, 'INITIALLY DEFERRED' => 8,
                default => 0,
            };
            $bits = ($seen[$scope] ?? 0) | $bit;
            if (($bits & 3) === 3 || ($bits & 12) === 12 || ($bits & 9) === 9) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:ConstraintAttributeSpec');
            } else {
                $seen[$scope] = $bits;
            }
        }
        return $sequence;
    }
}
