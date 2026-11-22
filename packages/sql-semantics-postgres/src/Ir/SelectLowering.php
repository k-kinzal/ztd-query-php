<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;

/**
 * Resolves the supported SELECT projection and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
        $statement = $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete PostgreSQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->where === null && $statement->groupBy === [] && $statement->having === null
                && $statement->windows === [] && $statement->distinct === null && $statement->into === null && $statement->options === null,
            'Complete PostgreSQL IR currently supports unfiltered, unordered bag projections.',
        );
        Check::input($statement->targets !== [], 'A complete SELECT needs a nonempty projection.');
        $binding = new SourceBinding($operation, $statement->from);
        $expressions = new ExpressionLowering();
        $searchPath = $operation->context->searchPath;
        $operators = $searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value;
        $fields = $operation->facts->output?->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        foreach ($statement->targets as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $results[] = new ResultColumn($expressions->lower($column->expression, $binding, $operators === 'pg_catalog'), new Identifier($name->value));
        }

        return new Select($results, $binding->source(), $profile->grammar);
    }
}
