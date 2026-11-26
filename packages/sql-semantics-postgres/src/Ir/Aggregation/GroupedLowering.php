<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall as ParsedAggregate;
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
        if ($statement->groupBy !== [] || $statement->having !== null) {
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

        $binding = new SourceBinding($operation, $statement->from);
        $keys = (new GroupedKeys())->lower($statement, $binding);
        $aggregate = new AggregateBinding($operation->context);
        $where = (new PredicateLowering())->lower($statement->where, $binding, $aggregate->catalogFirst);
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A grouped projection requires a resolved output shape.');
        $columns = [];
        foreach ($statement->targets as $index => $target) {
            Check::input($target instanceof Target, 'A grouped projection requires explicit result fields.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'A grouped result must have a resolved name.');
            $value = $target->expression instanceof ParsedAggregate
                ? $aggregate->lower($target->expression, $binding)
                : $binding->reference($target->expression);
            $columns[] = new GroupColumn($value, new Identifier($name->value));
        }
        $having = (new HavingLowering())->lower($statement->having, $binding, $aggregate);
        $source = $binding->source();
        Check::input($source !== null && $columns !== [], 'A grouped SELECT needs one resolved input and at least one named result.');

        $result = new ResultLowering();
        $order = $result->orders($operation, $statement, null, [], $aggregate->catalogFirst);

        return new GroupedSelect($source, $keys, $columns, $profile->grammar, $where, $order, $result->limit($statement->options?->limit), $having);
    }
}
