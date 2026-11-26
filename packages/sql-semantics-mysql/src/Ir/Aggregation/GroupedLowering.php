<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\Ordering\ProjectionLabel;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate as ParsedAggregate;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as Target;

/**
 * Resolves one grouped query into checked table, key and aggregate roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedLowering
{
    /**
     * Recognizes a query block using an explicit group or a known aggregate name.
     */
    public function applies(Select $statement): bool
    {
        if ($statement->groupBy !== null) {
            return true;
        }
        foreach ($statement->items as $target) {
            if ($target instanceof Target && $target->expression instanceof ParsedAggregate
                && AggregateOperator::tryFrom(strtolower($target->expression->function->value)) !== null) {
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
        Check::input($profile->lexical->equals(new LexicalSettings()), 'A grouped projection requires default MySQL lexical settings.');
        $binding = new SourceBinding($operation, $statement->from);
        $aggregate = new AggregateBinding();
        $grouping = new GroupedKeys();
        $keys = $grouping->lower($statement, $binding);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A grouped projection requires a resolved output shape.');
        $columns = [];
        $labels = new ProjectionLabel();
        foreach ($statement->items as $index => $target) {
            Check::input($target instanceof Target, 'A grouped projection requires explicit result fields.');
            $field = $fields->at($index);
            $name = $field->name;
            Check::input($name !== null, 'A grouped result must have a resolved name.');
            $value = $target->expression instanceof ParsedAggregate
                ? $aggregate->lower($target->expression, $binding)
                : $binding->reference($target->expression);
            $columns[] = new GroupColumn($value, $labels->lower($statement, $field, $name->value));
        }
        $having = (new HavingLowering())->lower($statement->having, $operation, $binding, $aggregate, $columns);
        $source = $binding->source();
        Check::input($source !== null, 'A grouped SELECT needs one resolved input and at least one named result.');

        return new GroupedSelect($source, $grouping->withNames($statement, $keys, $columns), $columns, $profile->grammar, $where, $having);
    }
}
