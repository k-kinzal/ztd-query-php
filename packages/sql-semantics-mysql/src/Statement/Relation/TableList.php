<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * Table references separated by commas: their cross product, binding more loosely than every JOIN.
 *
 * A comma list is never a member of another one; written in parentheses it
 * is a nested relation.
 *
 * Rule: MYSQL-TABLE-LIST-001. The members are derived from left to right
 * by MYSQL-FROM-SCOPE-001; a lateral member sees the members to its left.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html ("the precedence
 * of the comma operator is less than that of INNER JOIN"). Status: Implemented.
 *
 * @visibility public
 * @example Reading the members of a comma list
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 FROM t, u JOIN v ON u.a = v.a');
 *     [count($query->statement->from->members), $query->statement->from->members[1] instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable] // => [2, true]
 * @example Refusing a list of one member
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([new \SqlSemantics\Platform\MySql\Statement\Relation\Dual()]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class TableList implements Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<Relation> The members in written order
     */
    public readonly array $members;

    /**
     * @param list<Relation> $members The members in written order; at least two
     */
    public function __construct(array $members)
    {
        $this->members = Check::listOf($members, Relation::class, 'A comma list joins at least two table references.', 2);
        foreach ($this->members as $member) {
            Check::input(!$member instanceof self, 'A comma list used as a member is written in parentheses.');
        }
    }

    /**
     * Derives the members and the shape of their combined rows.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the members separated by commas.
     */
    public function render(Output $out): void
    {
        $out->list($this->members);
    }
}
