<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A join of two FROM items.
 *
 * Mirrors PostgreSQL's `JoinExpr` without alias. A CROSS join has no
 * condition, a NATURAL join merges the columns both sides share, every other
 * join has an ON or USING condition. Joins associate from the left; a join on
 * the right of a CROSS or NATURAL join needs parentheses, so such an operand
 * is refused. The facts follow PG-JOIN-001.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN. Status: Implemented.
 *
 * @visibility public
 * @example Reading a join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM a NATURAL LEFT OUTER JOIN b');
 *     [$query->statement->from->kind->value, $query->statement->from->natural, $query->toString()] // => ['LEFT', true, 'SELECT 1 FROM a NATURAL LEFT JOIN b']
 * @example Refusing a qualified join without a condition
 *     $a = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('a'))));
 *     $b = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('b'))));
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Join($a, \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Left, $b) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Join implements Relation
{
    use Snapshot;

    /**
     * @param Relation $left The left side
     * @param JoinKind $kind The kind of join
     * @param Relation $right The right side
     * @param JoinOn|JoinUsing|null $condition The join condition
     * @param bool $natural Whether NATURAL is written
     */
    public function __construct(
        public readonly Relation $left,
        public readonly JoinKind $kind,
        public readonly Relation $right,
        public readonly JoinOn|JoinUsing|null $condition = null,
        public readonly bool $natural = false,
    ) {
        Check::input(!$left instanceof RelationList && !$right instanceof RelationList, 'A join side is one FROM item.');
        Check::input($kind !== JoinKind::Cross || (!$natural && $condition === null), 'A CROSS join has no condition.');
        Check::input(!$natural || $condition === null, 'A NATURAL join has no condition.');
        Check::input($kind === JoinKind::Cross || $natural || $condition !== null, 'A qualified join has an ON or USING condition.');
        Check::input($condition !== null || !$right instanceof self, 'A join on the right of a CROSS or NATURAL join needs parentheses.');
    }

    /**
     * Derives both sides, the condition and the joined columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the sides around NATURAL, the join kind and JOIN, then the condition.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->natural) {
            $out->keyword('NATURAL');
        }
        $out->keyword(...$this->kind->keywords())->keyword('JOIN')->node($this->right)->node($this->condition);
    }
}
