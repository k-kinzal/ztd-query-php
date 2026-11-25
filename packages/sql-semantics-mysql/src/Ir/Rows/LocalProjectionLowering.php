<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithLowering;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
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
    public function lower(BoundStatement $operation, ?ParsedSelect $query = null, int $remaining = 16): LocalProjection
    {
        Check::input($remaining >= 1 && $remaining <= 16, 'A local projection has at most sixteen definition boundaries.');
        $statement = $query ?? $operation->statement;
        Check::input($statement instanceof ParsedSelect && $statement->from instanceof DerivedTable, 'A local projection requires a query with a local source.');
        $local = new LocalDefinition();
        $local->consumer($statement);
        $definition = $local->resolve($statement->from);
        $source = match (true) {
            $definition instanceof QueryExpression => (new WithLowering())->lower($operation, $definition),
            $definition->from instanceof DerivedTable => $this->lower($operation, $definition, $remaining - 1),
            default => (new SelectLowering())->lower($operation, $definition),
        };
        if ($statement->from->columns === []) {
            (new LocalProjectionAliases())->check($source);
        }
        $width = $source instanceof WithProjection ? $source->width() : count($source->columns);
        Check::input(count($operation->facts->relation($statement->from)->shape->slots) === $width, 'The local definition must resolve all output positions.');

        return new LocalProjection($source, $local->columns($operation, $statement, $statement->from));
    }
}
