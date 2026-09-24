<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Statement\Retrieval\RetrievedQuery;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SELECT ... INTO targets in a stored program, storing the single result row in local and user variables, one per column.
 * @visibility public
 * @example Reading local SELECT INTO targets
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE PROCEDURE p(OUT total INT) SELECT COUNT(*) INTO total FROM t');
 *     $statement->body->targets[0]->variable->name // => 'total'
 */
final class SelectIntoStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<LocalVariableReference|VariableReference|UnresolvedVariableReference>
     */
    public readonly array $targets;

    /**
     * Requires a MySQL query, at least one local variable target and one target per known result column.
     * @param list<LocalVariableReference|VariableReference|UnresolvedVariableReference> $targets
     * @throws InvalidStructure
     */
    public function __construct(public readonly BoundQuery $query, array $targets)
    {
        Collections::alternatives($targets, [LocalVariableReference::class, VariableReference::class, UnresolvedVariableReference::class]);
        RetrievedQuery::check($query->origin, $query, Dialect::MySql);
        $width = RetrievedQuery::width($query);
        if (array_filter($targets, static fn (object $target): bool => $target instanceof LocalVariableReference) === [] || ($width !== null && $width !== count($targets))) {
            throw new InvalidStructure('A stored program SELECT INTO stores one column in each target, at least one of them local.');
        }
        $this->targets = Collections::nonEmpty($targets);
    }
}
