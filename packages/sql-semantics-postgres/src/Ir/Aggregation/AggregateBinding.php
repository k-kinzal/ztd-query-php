<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall as ParsedAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;

/**
 * Binds the native builtin aggregate and its position in the grouped input.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AggregateBinding
{
    /**
     * Whether unqualified exact signatures search the builtin catalog first.
     */
    public readonly bool $catalogFirst;

    private readonly bool $exclusiveCatalog;

    /**
     * Selects the schema policies that determine native builtin resolution.
     */
    public function __construct(AnalysisContext $context)
    {
        $first = null;
        $only = true;
        foreach ($context->searchPath as $schema) {
            if ($schema->value === 'pg_temp') {
                continue;
            }
            $first ??= $schema->value;
            $only = $only && $schema->value === 'pg_catalog';
        }
        $this->catalogFirst = $first === 'pg_catalog';
        $this->exclusiveCatalog = $only;
    }

    /**
     * Resolves the builtin operator and its written argument role.
     */
    public function lower(ParsedAggregate $expression, SourceBinding $binding): Aggregate
    {
        $operator = AggregateOperator::tryFrom($expression->name->last()->value);
        Check::input(
            $operator !== null && $expression->over === null && $expression->filter === null
                && $expression->order === [] && !$expression->withinGroup && !$expression->variadic
                && ($expression->star || count($expression->arguments) === 1),
            'The grouped aggregate requires one supported builtin without a window, filter or ordering.',
        );
        $parts = $expression->name->parts;
        $qualified = count($parts) === 2 && $parts[0]->value === 'pg_catalog';
        Check::input($qualified || (count($parts) === 1 && $this->catalogFirst), 'The aggregate must resolve to pg_catalog.');
        Check::input(
            $qualified || $operator !== AggregateOperator::Count || $expression->star || $this->exclusiveCatalog,
            'An unqualified COUNT argument needs an exclusive pg_catalog function search path.'
        );
        if ($expression->star) {
            return new Aggregate($operator);
        }
        $argument = $expression->arguments[0];
        Check::input($argument instanceof PositionalArgument, 'The grouped aggregate requires a positional argument.');

        return new Aggregate($operator, $binding->reference($argument->value), $expression->distinct);
    }
}
