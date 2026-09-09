<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Column;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * processCASbits permits attributes according to the non-null output pointers at each call site.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L19273-L19341
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L4127-L4307
 */
final class ConstraintCapabilitiesRule implements RewriteRule
{
    /**
     * Removes unsupported attributes while preserving compatible and redundant specifications.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('ConstraintAttributeElem') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $owner = null;
            foreach (['ConstraintElem', 'DomainConstraintElem', 'alter_table_cmd', 'CreateTrigStmt'] as $rule) {
                $owner ??= $origin->ancestor($rule);
            }
            $scope = $owner === null ? null : $sequence->range($owner);
            if ($scope === null) {
                continue;
            }
            $kind = $sequence->nameAt($scope[0]);
            $option = implode(' ', array_slice($sequence->names(), $range[0], $range[1] - $range[0]));
            $permitted = match ($option) {
                'DEFERRABLE', 'INITIALLY DEFERRED' => !in_array($kind, ['CHECK', 'NOT'], true),
                'NOT VALID' => in_array($kind, ['CHECK', 'FOREIGN'], true),
                'NO INHERIT' => in_array($kind, ['CHECK', 'NOT'], true),
                default => true,
            };
            if (!$permitted) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:processCASbits:' . $option);
            }
        }
        return $sequence;
    }
}
