<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * build.c:sqlite3AddGenerated permits one generated expression without a DEFAULT on the same column.
 */
final class GeneratedColumnRule implements RewriteRule
{
    /**
     * Retains the first generated expression and removes conflicting column value definitions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $columns = [];
        $generated = [];
        foreach (array_reverse($sequence->occurrences('ccons')) as $constraint) {
            $range = $sequence->range($constraint);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $isGenerated = $sequence->child($constraint, 'generated') !== null;
            if (!$isGenerated && $origin->name !== 'DEFAULT') {
                continue;
            }
            $position = array_search('column', $origin->rules, true);
            if ($position === false) {
                $position = array_search('carglist', $origin->rules, true);
            }
            $column = $position === false ? $constraint : $origin->ancestors[$position];
            $columns[$column][] = $constraint;
            if ($isGenerated) {
                $generated[$column] ??= $constraint;
            }
        }
        foreach ($columns as $column => $constraints) {
            if (!isset($generated[$column])) {
                continue;
            }
            foreach ($constraints as $constraint) {
                $range = $sequence->range($constraint);
                if ($constraint !== $generated[$column] && $range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'src/build.c:sqlite3AddGenerated:column-expression');
                }
            }
        }
        return $sequence;
    }
}
