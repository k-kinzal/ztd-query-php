<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\ParameterLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as ParsedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

/**
 * Resolves the supported SELECT and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectLowering
{
    /**
     * Converts the checked operation into a complete value.
     * @throws InvalidConstruction When the operation cannot be represented in this domain
     */
    public function lower(BoundStatement $operation, ?ParsedSelect $operand = null): Select
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The projection requires the native language profile.');
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This projection profile requires the default lexical settings.');
        $statement = $operand ?? $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete MySQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->groupBy === null && $statement->having === null
                && $statement->windows === [] && $statement->qualify === null
                && $statement->procedure === null && $statement->locking === []
                && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All] || $statement->options === [SelectOption::Distinct]),
            'Complete MySQL IR currently supports projections with positional result selection.',
        );
        $expressions = new ExpressionLowering();
        $binding = JoinBinding::bind($operation, $statement->from instanceof Dual ? null : $statement->from);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        $parameters = new ParameterLowering();
        $parameterPosition = 0;
        foreach ($statement->items as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $parameter = $parameters->lower($column->expression, $parameterPosition + 1);
            if ($parameter !== null) {
                ++$parameterPosition;
            }
            $results[] = new ResultColumn($parameter ?? $expressions->lower($column->expression, $binding), new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        $result = new ResultLowering();
        $order = [];
        foreach ($statement->orderBy as $key) {
            $order[] = $result->order($key);
        }

        return new Select($results, $binding->source(), $profile->grammar, $where, $statement->options === [SelectOption::Distinct], $order, $result->limit($statement->limit));
    }
}
