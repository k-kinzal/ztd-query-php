<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;

/**
 * A conflict handler with a typed inference target and operation-specific operands.
 * @visibility public
 * @example Reading the derived conflict operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1) ON CONFLICT(id) DO UPDATE SET id=2');
 *     $statement->conflicts[0]->action->value // => 'update'
 */
abstract class ConflictAction
{
    /**
     * Conflict operation derived from the concrete action type.
     */
    public readonly Conflict\ActionKind $action;

    /**
     * Retains the conflict inference target and derives the action from its concrete form.
     */
    public function __construct(public readonly Conflict\Target $target, public readonly Node $source)
    {
        $this->action = $this->operation();
    }

    abstract protected function operation(): Conflict\ActionKind;
}
