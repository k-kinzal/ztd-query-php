<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Implements gram.y's two-argument row requirement for each side of OVERLAPS.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class OverlapsArgumentsRule implements RewriteRule
{
    /**
     * Preserves valid rows and the first two chosen expressions; supplies NULL for missing arguments.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (array_reverse($sequence->terminals) as $terminal) {
            if ($terminal->name !== 'OVERLAPS' || !$terminal->within('a_expr')) {
                continue;
            }
            $parent = $terminal->ancestor('a_expr');
            foreach ($sequence->productions as $production) {
                if ($production->parent === $parent && $production->rule === 'row') {
                    $sequence = $this->pair($sequence, $production->id);
                }
            }
        }
        return $sequence;
    }

    /**
     * Finds row elements through expr_list only, excluding expressions nested inside another element.
     * @return list<int>
     */
    public function elements(TerminalSequence $sequence, int $row): array
    {
        $parents = [$row => true];
        $elements = [];
        foreach ($sequence->productions as $production) {
            if ($production->parent === null || !isset($parents[$production->parent])) {
                continue;
            }
            if ($production->rule === 'expr_list') {
                $parents[$production->id] = true;
            } elseif ($production->rule === 'a_expr') {
                $elements[] = $production->id;
            }
        }
        return $elements;
    }

    /**
     * Rebuilds only a row whose argument count violates the source action.
     */
    public function pair(TerminalSequence $sequence, int $row): TerminalSequence
    {
        $elements = $this->elements($sequence, $row);
        $range = $sequence->range($row);
        $source = 'gram.y:OVERLAPS-row-arity';
        if (count($elements) === 2 || $range === null || $sequence->terminals[$range[0]]->rewrite === $source) {
            return $sequence;
        }
        $output = [$sequence->insertedFor('ROW', $row, $source), $sequence->insertedFor('(', $row, $source, 1)];
        for ($index = 0; $index < 2; ++$index) {
            if ($index !== 0) {
                $output[] = $sequence->insertedFor(',', $row, $source, 3);
            }
            $element = isset($elements[$index]) ? $sequence->range($elements[$index]) : null;
            array_push($output, ...($element === null
                ? [$sequence->insertedFor('NULL_P', $row, $source, 2 + $index * 2)]
                : array_slice($sequence->terminals, $element[0], $element[1] - $element[0])));
        }
        $output[] = $sequence->insertedFor(')', $row, $source, 5);
        return $sequence->replace($range[0], $range[1] - $range[0], $output, $source);
    }
}
