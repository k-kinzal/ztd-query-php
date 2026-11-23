<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
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
    public function lower(BoundStatement $operation, ?ParsedSelect $query = null, int $remaining = 16): LocalProjection
    {
        Check::input($remaining >= 1 && $remaining <= 16, 'A local projection has at most sixteen definition boundaries.');
        $statement = $query ?? $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'A local projection requires a query with a local source.');
        $local = new LocalDefinition();
        $body = $local->consumer($statement);
        Check::input($body->from !== null, 'A local projection requires a query with a local source.');
        $definition = $local->resolve($body);
        $source = match (true) {
            $definition instanceof QueryExpression => (new WithLowering())->lower($operation, $definition),
            $definition instanceof ValuesList => (new ValuesLowering())->lower($operation, $definition),
            $definition->from instanceof DerivedTable => $this->lower($operation, $definition, $remaining - 1),
            default => (new SelectLowering())->lower($operation, $definition),
        };
        $inputs = $operation->facts->relation($body->from)->shape->slots;
        $fields = $operation->facts->query($body)->fields();
        $width = match (true) {
            $source instanceof ValuesQuery => count($source->rows[0]->cells),
            $source instanceof WithProjection => $source->width(),
            $source instanceof LocalProjection, $source instanceof Select => count($source->columns),
        };
        Check::input($fields !== null && count($inputs) === $width, 'The local definition must resolve all output positions.');
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
