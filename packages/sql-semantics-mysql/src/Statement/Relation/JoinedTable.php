<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A join of two table references with its ON condition or USING column list.
 *
 * The structure follows the parse of the release, where a join can nest on
 * the right without parentheses (`t JOIN u JOIN v ON c` joins t to the join
 * of u and v in 8.0 and later). A natural join takes no condition and its
 * right operand is no unparenthesized join; LEFT and RIGHT joins require a
 * condition; a comma list as an operand is written in parentheses.
 *
 * Rule: MYSQL-JOIN-001. The operands and the condition are derived by
 * MYSQL-FROM-SCOPE-001 and combined by MYSQL-JOIN-COLUMNS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 FROM t LEFT JOIN u USING (a)');
 *     [$query->statement->from->operator, $query->statement->from->using[0]->value] // => [\SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Left, 'a']
 * @example Refusing a left join without a condition
 *     $t = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
 *     $u = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')));
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable($t, \SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator::Left, $u) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JoinedTable implements Relation
{
    use Snapshot;

    /**
     * @var list<Name> The USING columns in written order; empty without USING
     */
    public readonly array $using;

    /**
     * @param Relation $left The left operand
     * @param JoinOperator $operator The join operator
     * @param Relation $right The right operand
     * @param Scalar|null $on The ON condition
     * @param list<Name> $using The USING columns
     */
    public function __construct(public readonly Relation $left, public readonly JoinOperator $operator, public readonly Relation $right, public readonly ?Scalar $on = null, array $using = [])
    {
        $this->using = Check::listOf($using, Name::class, 'USING names columns.');
        Check::input($on === null || $using === [], 'A join has an ON condition or a USING list, not both.');
        Check::input(!$operator->natural() || ($on === null && $using === []), 'A natural join takes no condition.');
        Check::input(!$operator->conditioned() || $on !== null || $using !== [], 'A LEFT or RIGHT join requires an ON condition or a USING list.');
        Check::input(!$operator->natural() || !$right instanceof self, 'The right operand of a natural join is no unparenthesized join.');
        Check::input(!$left instanceof TableList && !$right instanceof TableList, 'A comma list joined to a table is written in parentheses.');
    }

    /**
     * Derives the operands, the condition and the shape of the joined rows.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the operands, the operator and the condition.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword(...$this->operator->keywords())->node($this->right);
        if ($this->on !== null) {
            $out->keyword('ON')->node($this->on);
        }
        if ($this->using !== []) {
            $out->keyword('USING')->symbol('(');
            foreach ($this->using as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
    }
}
