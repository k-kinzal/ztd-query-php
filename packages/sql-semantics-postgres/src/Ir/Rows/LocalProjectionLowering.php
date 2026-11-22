<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;

/**
 * Closes the local slot layout and preserves the outer result names.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class LocalProjectionLowering
{
    /**
     * Publishes a local projection only after resolving its exact definition.
     *
     * @throws InvalidConstruction When a result does not address a checked local slot
     */
    public function lower(BoundStatement $operation, Select|QueryExpression|null $query = null, int $remaining = 16): LocalProjection
    {
        Check::input($remaining >= 1 && $remaining <= 16, 'A local projection has at most sixteen definition boundaries.');
        $statement = $query ?? $operation->statement;
        Check::input($statement instanceof Select || $statement instanceof QueryExpression, 'A local projection requires a query with a local source.');
        $body = $statement instanceof QueryExpression ? $statement->body : $statement;
        Check::input($body instanceof Select && $body->from !== null, 'A local projection requires a query with a local source.');
        Check::input(
            $body->where === null && $body->groupBy === [] && $body->having === null
            && $body->windows === [] && $body->distinct === null && $body->into === null && $body->options === null,
            'The local projection requires an ordinary result without outer clauses.'
        );
        $definition = (new LocalDefinition())->resolve($operation, $body, $statement);
        $source = $definition instanceof QueryExpression || $definition->from instanceof DerivedTable
            ? $this->lower($operation, $definition, $remaining - 1)
            : (new SelectLowering())->lower($operation, $definition);
        $inputs = $operation->facts->relation($body->from)->shape->slots;
        $fields = $operation->facts->query($body)->fields();
        Check::input($fields !== null && count($inputs) === count($source->columns), 'The local definition must resolve all output positions.');
        $results = [];
        foreach ($body->targets as $position => $item) {
            Check::input($item instanceof ExpressionTarget, 'A local projection requires explicit output columns.');
            $resolution = $operation->facts->scalar($item->expression)->resolution;
            Check::input(
                $resolution instanceof ResolvedColumn && $resolution->relation === $body->from && $resolution->depth === 0,
                'Each local result must read a resolved local slot.'
            );
            $slot = array_search($resolution->slot, $inputs, true);
            $name = $fields->at($position)->name;
            Check::input($slot !== false && $name !== null, 'A local result needs a named, resolved slot.');
            $results[] = new LocalResultColumn($slot, new Identifier($name->value));
        }

        return new LocalProjection($source, $results);
    }
}
