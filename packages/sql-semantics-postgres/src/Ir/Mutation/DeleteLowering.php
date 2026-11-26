<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete as ParsedDelete;

/**
 * Publishes a checked single-target DELETE from resolved analysis facts.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DeleteLowering
{
    /**
     * Closes the filter and output layout before exporting the target's slots.
     */
    public function lower(BoundStatement $operation, ParsedDelete $statement): Delete
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The DELETE requires the native language profile.');
        Check::input($statement->with === null && $statement->using === null
            && !$statement->where instanceof CurrentOf, 'Complete PostgreSQL DELETE requires one target without WITH, USING or a cursor.');
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        $binding = new SourceBinding($operation, $statement->target);
        $where = (new PredicateLowering())->lower($statement->where, $binding, $catalogFirst);
        $returning = $statement->returning === [] ? [] : (new ReturningLowering())->lower($operation->facts->output, $binding);
        $target = $binding->source();
        Check::input($target !== null, 'A complete DELETE requires a resolved target.');

        return new Delete($target, $where, $profile->grammar, $returning);
    }
}
