<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Keeps assignments and dependent selected subqueries behind a materialization boundary.
 *
 * Scalar wrappers eliminated by the release's reduction rules are inspected as their
 * selected expression. An independent subquery can remain in a merged projection;
 * a retained subquery reading an outer column cannot. Verified through SQL and EXPLAIN
 * on MySQL 8.4, with legacy correlated-column metadata from Testcontainers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class MergeDependencies
{
    /**
     * Tells whether selected expressions need their own materialized query block.
     *
     * @param list<object> $parts The selected expressions
     */
    public function separate(array $parts, GrammarRelease $release, ?Facts $facts = null): bool
    {
        $pending = $parts;
        while ($pending !== []) {
            $part = array_pop($pending);
            while ($part instanceof ScalarSubquery) {
                $replacement = (new ScalarReduction())->expression($part->query, $release);
                $part = $replacement ?? $part->query;
            }
            if (($part instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery || $part instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison)
                && (new ScalarReduction())->expression($part->query) === null && ($facts === null || (new SubqueryRows())->stored($part, $release) !== null || $this->external($part->operand, $facts))) {
                return true;
            }
            if ($part instanceof VariableAssignment || ($part instanceof Query && ($facts === null || $this->external($part, $facts) || $this->changing($part, $release)))) {
                return true;
            }
            if (is_object($part) && !$part instanceof Query) {
                array_push($pending, ...array_values(get_object_vars($part)));
            } elseif (is_array($part)) {
                array_push($pending, ...array_values($part));
            }
        }

        return false;
    }

    /**
     * Tells whether a retained query contains a call or variable access that can change per read.
     */
    public function changing(Query $query, GrammarRelease $release): bool
    {
        $pending = [$query];
        $effects = new \SqlSemantics\Platform\MySql\Rules\Call\CallEffects();
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof Node && $effects->uncacheable($node, $release)) {
                return true;
            }
            if (is_object($node)) {
                array_push($pending, ...array_values(get_object_vars($node)));
            } elseif (is_array($node)) {
                array_push($pending, ...array_values($node));
            }
        }

        return false;
    }

    /**
     * Tells whether a query reads a column outside its own nested query blocks.
     * Query-expression wrappers do not introduce a name-resolution level. A non-lateral
     * derived table skips its containing FROM block, as its environment does.
     */
    public function external(Node $node, Facts $facts, int $level = 0): bool
    {
        if ($node instanceof ColumnUse) {
            $resolution = $facts->covers($node) ? $facts->scalar($node)->resolution : null;

            return ($resolution instanceof ResolvedColumn || $resolution instanceof AliasTarget) && $resolution->depth >= $level;
        }
        if ($node instanceof DerivedTable && !$node->lateral && $level > 0) {
            $level--;
        }
        $level += $node instanceof Select ? 1 : 0;
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            if ($this->external($child, $facts, $level)) {
                return true;
            }
        }

        return false;
    }
}
