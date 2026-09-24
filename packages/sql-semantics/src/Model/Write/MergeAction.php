<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * A match decision; each concrete effect supplies only its own operands.
 * @visibility public
 * @example Reading a decision's match and operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED AND s.id>0 THEN DELETE');
 *     [$statement->merge->actions[0]->match->value, $statement->merge->actions[0]->action->value] // => ['matched', 'delete']
 */
abstract class MergeAction
{
    /**
     * Conditional write operation derived from the concrete action type.
     */
    public readonly Decision\ActionKind $action;

    /**
     * Retains the row-match category and optional action predicate in decision order.
     */
    public function __construct(public readonly Decision\MatchKind $match, public readonly ?Expression $condition, public readonly Node $source)
    {
        $this->action = $this->operation();
    }

    abstract protected function operation(): Decision\ActionKind;
}
