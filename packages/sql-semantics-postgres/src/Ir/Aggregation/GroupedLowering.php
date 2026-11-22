<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall as ParsedAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget as Target;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

/**
 * Resolves one grouped query into checked table, key and aggregate roles.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GroupedLowering
{
    /**
     * Recognizes a query block using an explicit group or a known aggregate name.
     */
    public function applies(Select $statement): bool
    {
        if ($statement->groupBy !== []) {
            return true;
        }
        foreach ($statement->targets as $target) {
            if ($target instanceof Target && $target->expression instanceof ParsedAggregate
                && AggregateOperator::tryFrom($target->expression->name->last()->value) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Binds the input occurrence and publishes the completed grouped query.
     */
    public function lower(BoundStatement $operation, Select $statement): GroupedSelect
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'A grouped projection requires the native language profile.');

        $this->checkClauses($statement);
        $binding = new SourceBinding($operation, $statement->from);
        $keys = $this->keys($statement, $binding);
        $where = (new PredicateLowering())->lower($statement->where, $binding, $this->catalogFirst($operation->context));
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A grouped projection requires a resolved output shape.');
        $columns = [];
        foreach ($statement->targets as $index => $target) {
            Check::input($target instanceof Target, 'A grouped projection requires explicit result fields.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'A grouped result must have a resolved name.');
            $value = $target->expression instanceof ParsedAggregate
                ? $this->aggregate($target->expression, $binding, $operation->context)
                : $binding->reference($target->expression);
            $columns[] = new GroupColumn($value, new Identifier($name->value));
        }
        $source = $binding->source();
        Check::input($source !== null && $columns !== [], 'A grouped SELECT needs one resolved input and at least one named result.');

        return new GroupedSelect($source, $keys, $columns, $profile->grammar, $where);
    }

    private function checkClauses(Select $statement): void
    {
        Check::input(
            $statement->having === null && $statement->windows === [] && $statement->distinct === null
                && $statement->into === null && $statement->groupQuantifier === null
                && ($statement->options->order ?? []) === [] && $statement->options?->limit === null
                && ($statement->options->locking ?? []) === [] && !($statement->options->readOnly ?? false),
            'The grouped projection requires an ordinary unbounded GROUP BY without result modifiers.',
        );
    }

    /**
     * @return list<ColumnReference>
     */
    private function keys(Select $statement, SourceBinding $binding): array
    {
        $keys = [];
        foreach ($statement->groupBy as $key) {
            Check::input($key instanceof Scalar, 'A grouped projection requires simple grouping keys.');
            $keys[] = $binding->reference($key);
        }

        return $keys;
    }

    private function aggregate(ParsedAggregate $expression, SourceBinding $binding, AnalysisContext $context): Aggregate
    {
        $operator = AggregateOperator::tryFrom($expression->name->last()->value);
        Check::input(
            $operator !== null && $expression->over === null && $expression->filter === null
                && $expression->order === [] && !$expression->withinGroup && !$expression->variadic
                && ($expression->star || count($expression->arguments) === 1),
            'The grouped aggregate requires one supported builtin without a window, filter or ordering.',
        );
        $this->checkBuiltin($expression, $operator, $context);
        if ($expression->star) {
            return new Aggregate($operator);
        }
        $argument = $expression->arguments[0];
        Check::input($argument instanceof PositionalArgument, 'The grouped aggregate requires a positional argument.');

        return new Aggregate($operator, $binding->reference($argument->value), $expression->distinct);
    }

    private function checkBuiltin(ParsedAggregate $call, AggregateOperator $operator, AnalysisContext $context): void
    {
        $parts = $call->name->parts;
        if (count($parts) === 2 && $parts[0]->value === 'pg_catalog') {
            return;
        }
        Check::input(count($parts) === 1 && $this->catalogFirst($context), 'The aggregate must resolve to pg_catalog.');
        if ($operator === AggregateOperator::Count && !$call->star) {
            foreach ($context->searchPath as $schema) {
                Check::input(
                    $schema->value === 'pg_temp' || $schema->value === 'pg_catalog',
                    'An unqualified COUNT argument needs an exclusive pg_catalog function search path.'
                );
            }
        }
    }

    private function catalogFirst(AnalysisContext $context): bool
    {
        foreach ($context->searchPath as $schema) {
            if ($schema->value !== 'pg_temp') {
                return $schema->value === 'pg_catalog';
            }
        }

        return false;
    }
}
