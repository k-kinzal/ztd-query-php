<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate as ParsedAggregate;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as Target;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

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
        $this->checkClauses($statement);
        $binding = new SourceBinding($operation, $statement->from);
        $keys = $this->keys($statement, $binding);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A grouped projection requires a resolved output shape.');
        $columns = [];
        foreach ($statement->items as $index => $target) {
            Check::input($target instanceof Target, 'A grouped projection requires explicit result fields.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'A grouped result must have a resolved name.');
            $value = $target->expression instanceof ParsedAggregate
                ? $this->aggregate($target->expression, $binding)
                : $binding->reference($target->expression);
            $columns[] = new GroupColumn($value, new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }
        $source = $binding->source();
        Check::input($source !== null && $columns !== [], 'A grouped SELECT needs one resolved input and at least one named result.');

        return new GroupedSelect($source, $keys, $columns, $profile->grammar, $where);
    }

    private function checkClauses(Select $statement): void
    {
        Check::input(
            $statement->having === null && $statement->windows === [] && $statement->qualify === null
                && $statement->orderBy === [] && $statement->limit === null && $statement->procedure === null
                && $statement->locking === [] && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All]),
            'The grouped projection requires an ordinary unbounded GROUP BY without result modifiers.',
        );
    }

    /**
     * @return list<ColumnReference>
     */
    private function keys(Select $statement, SourceBinding $binding): array
    {
        $keys = [];
        Check::input($statement->groupBy?->modifier === null, 'A grouped projection requires simple grouping keys.');
        foreach ($statement->groupBy->items ?? [] as $key) {
            Check::input($key->direction === null, 'A grouped projection requires simple grouping keys without a direction.');
            $keys[] = $binding->reference($key->expression);
        }

        return $keys;
    }

    private function aggregate(ParsedAggregate $expression, SourceBinding $binding): Aggregate
    {
        $operator = AggregateOperator::tryFrom(strtolower($expression->function->value));
        Check::input(
            $operator !== null && $expression->over === null && count($expression->arguments) <= 1,
            'The grouped aggregate requires one supported builtin without a window.'
        );
        $argument = $expression->arguments[0] ?? null;

        return new Aggregate($operator, $argument === null ? null : $binding->reference($argument), $expression->distinct);
    }
}
