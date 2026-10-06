<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * Table references written in parentheses: a nested join.
 *
 * Rule: MYSQL-NESTED-JOIN-001. The parentheses group the references for
 * joining and change no name: every table inside stays visible by its own
 * name. Source: https://dev.mysql.com/doc/refman/8.4/en/nested-join-optimization.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a nested join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 FROM t LEFT JOIN (u, v) ON t.a = u.a');
 *     $query->statement->from->right->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList // => true
 */
final class NestedRelation implements Relation
{
    use Snapshot;

    /**
     * @param Relation $relation The references inside the parentheses
     */
    public function __construct(public readonly Relation $relation)
    {
    }

    /**
     * Derives the references inside the parentheses.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the references in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->relation)->symbol(')');
    }
}
