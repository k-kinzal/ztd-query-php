<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\RecursiveSeries;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier;

/**
 * Links the anchor, recursive step and consumer before publishing the complete query.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
            $with !== null && $with->recursive && count($with->tables) === 1 && $statement->options === null,
            'A recursive series requires one unmodified recursive WITH definition.'
        );
        $definition = $with->tables[0];
        Check::input(
            $definition->materialization === null && $definition->search === null && $definition->cycle === null,
            'The recursive series uses ordinary materialization and no SEARCH or CYCLE clause.'
        );
        $query = $definition->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof SetOperation && $query->operator === SetOperator::Union
            && $query->left instanceof Select && $query->right instanceof Select, 'A recursive series requires an anchor followed by one UNION step.');
        $anchor = (new SelectLowering())->lower($operation, $query->left);
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
        foreach ($body->targets as $ordinal => $output) {
            Check::input($output instanceof ExpressionTarget, 'A recursive series requires explicit output columns.');
            $binding->read($operation, $output->expression, $source);
            $name = $fields->at($ordinal)->name;
            Check::input($name !== null, 'A recursive series needs named, resolved output fields.');
            $columns[] = new LocalResultColumn(0, new Identifier($name->value));
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
