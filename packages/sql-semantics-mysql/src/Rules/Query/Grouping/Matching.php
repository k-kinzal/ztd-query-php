<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use UnitEnum;

/**
 * Matches an expression with the GROUP BY expressions or select items it is written as.
 *
 * The grouping check (MYSQL-ONLY-FULL-GROUP-BY-001) accepts a select item or an ORDER BY key
 * that is written as a GROUP BY expression, and a DISTINCT block an ORDER BY key written as a
 * select item. Two expressions are written alike when they are of the same classes with the same
 * values, inside any parentheses; names compare without regard to case, and two column names
 * that resolve to the same column of the same occurrence are the same however they are
 * qualified (`t.a + 1` is `a + 1`, verified on a live 8.4 server). A key that is an alias or a
 * position stands for the select item it names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Matching
{
    /**
     * Tells whether an expression is written as one of a list of expressions.
     *
     * @param list<Scalar> $expressions
     */
    public function listed(Scalar $expression, array $expressions, ?Facts $facts = null): bool
    {
        foreach ($expressions as $candidate) {
            if ($this->same($this->unwrap($expression), $this->unwrap($candidate), $facts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether two parts of a statement are written alike: same classes and values, column names without regard to case, and with facts two column names that resolve to the same column of the same occurrence however they are qualified.
     *
     * The parts are compared property by property, through lists and nested parts; an enum case
     * is the same only as itself, and any other value only as an identical one.
     */
    public function same(Node $left, Node $right, ?Facts $facts = null): bool
    {
        $pending = [[$left, $right]];
        while ($pending !== []) {
            [$first, $second] = array_pop($pending);
            if (is_array($first) && is_array($second)) {
                if (count($first) !== count($second)) {
                    return false;
                }
                foreach ($first as $index => $value) {
                    if (!array_key_exists($index, $second)) {
                        return false;
                    }
                    $pending[] = [$value, $second[$index]];
                }

                continue;
            }
            if (!is_object($first) || !is_object($second)) {
                if ($first !== $second) {
                    return false;
                }

                continue;
            }
            $alike = $this->alike($first, $second, $facts);
            if ($alike === false) {
                return false;
            }
            if ($alike === null) {
                $pending[] = [get_object_vars($first), get_object_vars($second)];
            }
        }

        return true;
    }

    /**
     * Compares two objects by what they are rather than by their properties: true when they are the same, false when they differ, and null when their properties decide.
     *
     * Objects of different classes differ, an enum case is the same only as itself, names compare
     * without regard to case, and two column names the facts resolve to columns are the same when
     * they name the same column of the same occurrence.
     */
    public function alike(object $first, object $second, ?Facts $facts): ?bool
    {
        if ($first::class !== $second::class || $first instanceof UnitEnum) {
            return $first === $second;
        }
        if ($first instanceof Name && $second instanceof Name) {
            return strcasecmp($first->value, $second->value) === 0;
        }

        return $first instanceof ColumnUse && $second instanceof ColumnUse ? $this->column($first, $second, $facts) : null;
    }

    /**
     * Tells whether two column names resolve to the same column of the same occurrence, or answers null when the facts do not resolve both to a column.
     */
    public function column(ColumnUse $first, ColumnUse $second, ?Facts $facts): ?bool
    {
        if ($facts === null || !$facts->covers($first) || !$facts->covers($second)) {
            return null;
        }
        $one = $facts->scalar($first)->resolution;
        $two = $facts->scalar($second)->resolution;
        if (!$one instanceof ResolvedColumn || !$two instanceof ResolvedColumn) {
            return null;
        }
        $reads = new ColumnReads();

        return $reads->key($one) === $reads->key($two);
    }

    /**
     * Answers the expression a GROUP BY or ORDER BY key reads: the select item an alias or a position names, or the key itself.
     */
    public function target(Scalar $key, Facts $facts): Scalar
    {
        $node = $this->unwrap($key);
        if ($facts->covers($node)) {
            $resolution = $facts->scalar($node)->resolution;
            if ($resolution instanceof AliasTarget && $resolution->field->expression !== null) {
                return $resolution->field->expression;
            }
        }

        return $key;
    }

    /**
     * Removes the parentheses around an expression.
     */
    public function unwrap(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression;
    }
}
