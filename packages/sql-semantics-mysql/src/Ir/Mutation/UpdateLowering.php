<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Update as ParsedUpdate;

/**
 * Publishes the write, predicate and scalar read slots of a single-target UPDATE.
 *
 * @visibility SqlSemantics\Platform\MySql
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
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This UPDATE profile requires the default lexical settings.');
        Check::input(
            $statement->with === null && !$statement->lowPriority && !$statement->ignore
            && count($statement->tables) === 1 && $statement->orderBy === [] && $statement->limit === null,
            'A complete UPDATE requires one ordinary target.'
        );
        $binding = new SourceBinding($operation, $statement->tables[0]);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $expressions = new ExpressionLowering();
        $assignments = [];
        foreach ($statement->assignments as $assignment) {
            $slot = $binding->slotFor($binding->writableColumn($assignment->column->name->value));
            $value = $expressions->lower($assignment->value, $binding);
            Check::input($value instanceof ColumnReference || $value instanceof IntegerLiteral || $value instanceof NullLiteral, 'An UPDATE assignee requires a signed literal, NULL or a row reference.');
            $assignments[] = new UpdateAssignment($slot, $value);
        }
        $target = $binding->source();
        Check::input($target !== null, 'An UPDATE requires a resolved target.');

        return new Update($target, $assignments, $where, $profile->grammar);
    }
}
