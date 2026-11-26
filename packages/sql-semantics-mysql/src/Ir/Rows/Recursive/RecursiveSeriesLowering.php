<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\Recursive;

use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\RecursiveSeries;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;

/**
 * Links the anchor, recursive step and consumer before publishing the complete query.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RecursiveSeriesLowering
{
    /**
     * Resolves the exact recursive working relation and emits its immutable definition.
     */
    public function lower(BoundStatement $operation, QueryExpression $statement): RecursiveSeries
    {
        $with = $statement->with;
        Check::input(
            $with instanceof With && $with->recursive && count($with->tables) === 1 && $statement->orderBy === [] && $statement->limit === null,
            'A recursive series requires one unmodified recursive WITH definition.'
        );
        $definition = $with->tables[0];
        $query = $definition->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof SetOperation && $query->operator === SetOperator::Union
            && $query->left instanceof Select && $query->right instanceof Select, 'A recursive series requires an anchor followed by one UNION step.');
        $anchor = (new SelectLowering())->lower($operation, $query->left);
        Check::input($anchor->mode === ProjectionMode::Empty, 'A recursive series requires empty sql_mode.');
        $start = $anchor->columns[0]->expression;
        Check::input($anchor->source === null && $anchor->where === null && !$anchor->distinct && $anchor->orderBy === [] && $anchor->limit === null
            && count($anchor->columns) === 1 && $start instanceof IntegerLiteral, 'A recursive series needs one signed literal anchor.');

        [$increment, $boundary, $comparison] = (new RecursiveStepLowering())->lower($operation, $query->right, $definition);
        $body = (new LocalDefinition())->consumer($statement);
        $binding = new RecursiveBinding();
        $source = $binding->source($operation, $body, $definition);
        $fields = $operation->facts->query($body)->fields();
        Check::input($fields !== null, 'A recursive series needs named, resolved output fields.');
        $columns = [];
        foreach ($body->items as $ordinal => $output) {
            Check::input($output instanceof SelectExpression, 'A recursive series requires explicit output columns.');
            $binding->read($operation, $output->expression, $source);
            $name = $fields->at($ordinal)->name;
            Check::input($name !== null, 'A recursive series needs named, resolved output fields.');
            $columns[] = new LocalResultColumn(0, new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        return new RecursiveSeries(
            $start,
            $increment,
            $boundary,
            $comparison,
            $query->quantifier === SetQuantifier::All,
            $columns,
            $anchor->release
        );
    }
}
