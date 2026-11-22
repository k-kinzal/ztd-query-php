<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment as ParsedAssignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update as ParsedUpdate;

/**
 * Publishes the write, predicate and scalar read slots of a single-target UPDATE.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class UpdateLowering
{
    /**
     * Resolves the ordered writes and closes them over the declared target.
     */
    public function lower(BoundStatement $operation, ParsedUpdate $statement): Update
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The UPDATE requires the native language profile.');
        Check::input($statement->with === null && $statement->from === null
            && !$statement->where instanceof CurrentOf, 'A complete UPDATE requires one ordinary target.');
        $binding = new SourceBinding($operation, $statement->target);
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        $where = (new PredicateLowering())->lower($statement->where, $binding, $catalogFirst);
        $expressions = new ExpressionLowering();
        $assignments = [];
        foreach ($statement->assignments as $assignment) {
            Check::input($assignment instanceof ParsedAssignment && $assignment->column->steps === [], 'An UPDATE requires scalar assignments to whole columns.');
            $slot = $binding->slotFor($binding->writableColumn($assignment->column->column->value));
            $value = $expressions->lower($assignment->value, $binding, $catalogFirst);
            Check::input($value instanceof ColumnReference || $value instanceof IntegerLiteral || $value instanceof NullLiteral, 'An UPDATE assignee requires a signed literal, NULL or a row reference.');
            $assignments[] = new UpdateAssignment($slot, $value);
        }
        $returning = $statement->returning === [] ? [] : (new ReturningLowering())->lower($operation->facts->output, $binding);
        $target = $binding->source();
        Check::input($target !== null, 'An UPDATE requires a resolved target.');

        return new Update($target, $assignments, $where, $profile->grammar, $returning);
    }
}
