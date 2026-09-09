<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y/check_func_name accepts String name components, excluding A_Star and A_Indices.
 */
final class FunctionNameRule implements RewriteRule
{
    /**
     * Replaces non-name components with named qualification, preserving ColId disambiguation and column subscripts.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('indirection_el') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            if (!$this->isFunctionName($origin->rules)) {
                continue;
            }
            if ($origin->name === '[' || ($origin->name === '.' && $sequence->nameAt($range[0] + 1) === '*')) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $origin->replaced('.', 'postgresql.function-name'),
                    $sequence->inserted('IDENT', $origin, 'postgresql.function-name'),
                ], 'postgresql.function-name');
            }
        }
        return $sequence;
    }
    /**
     * Checks the parent of the indirection chain so expressions inside argument types retain subscripts.
     * @param list<string> $rules
     */
    public function isFunctionName(array $rules): bool
    {
        $index = count($rules) - 2;
        while (($rules[$index] ?? null) === 'indirection') {
            --$index;
        }
        return in_array($rules[$index] ?? null, ['func_name', 'function_with_argtypes'], true);
    }
}
