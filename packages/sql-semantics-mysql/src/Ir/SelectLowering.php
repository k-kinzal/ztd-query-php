<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as ParsedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

/**
 * Resolves the supported SELECT projection and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectLowering
{
    /**
     * Converts the checked operation into a complete value.
     * @throws InvalidConstruction When the operation cannot be represented in this domain
     */
    public function lower(BoundStatement $operation): Select
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The projection requires the native language profile.');
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This projection profile requires the default lexical settings.');
        $statement = $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete MySQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->where === null && $statement->groupBy === null && $statement->having === null
                && $statement->windows === [] && $statement->qualify === null && $statement->orderBy === []
                && $statement->limit === null && $statement->procedure === null && $statement->locking === []
                && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All]),
            'Complete MySQL IR currently supports unfiltered, unordered bag projections.',
        );
        $binding = new SourceBinding($operation, $statement->from);
        $expressions = new ExpressionLowering();
        $fields = $operation->facts->output?->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        foreach ($statement->items as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $results[] = new ResultColumn($expressions->lower($column->expression, $binding), new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        return new Select($results, $binding->source(), $profile->grammar);
    }
}
