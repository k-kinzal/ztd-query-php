<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;

/**
 * Closes the local slot layout and preserves the outer result names.
 *
 * @visibility SqlSemantics\Platform\MySql
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
            $body->where === null && $body->groupBy === null && $body->having === null && $body->windows === []
            && $body->qualify === null && $body->orderBy === [] && $body->limit === null && $body->procedure === null
            && $body->locking === [] && $body->into === null && $body->late === null && ($body->options === [] || $body->options === [SelectOption::All]),
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
        foreach ($body->items as $position => $item) {
            Check::input($item instanceof SelectExpression, 'A local projection requires explicit output columns.');
            $resolution = $operation->facts->scalar($item->expression)->resolution;
            Check::input(
                $resolution instanceof ResolvedColumn && $resolution->relation === $body->from && $resolution->depth === 0,
                'Each local result must read a resolved local slot.'
            );
            $slot = array_search($resolution->slot, $inputs, true);
            $name = $fields->at($position)->name;
            Check::input($slot !== false && $name !== null, 'A local result needs a named, resolved slot.');
            $results[] = new LocalResultColumn($slot, new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        return new LocalProjection($source, $results);
    }
}
