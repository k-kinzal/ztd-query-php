<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete as ParsedDelete;

/**
 * Publishes a checked single-target DELETE from resolved analysis facts.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeleteLowering
{
    /**
     * Rejects unsupported clauses and closes the filter before exporting the target's slots.
     */
    public function lower(BoundStatement $operation, ParsedDelete $statement): Delete
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The DELETE requires the native language profile.');
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This DELETE profile requires the default lexical settings.');
        Check::input(
            $statement->with === null && $statement->options === [] && $statement->orderBy === [] && $statement->limit === null,
            'Complete MySQL DELETE requires a single unmodified target without ordering or bounds.'
        );
        $binding = new SourceBinding($operation, $statement->table);
        $where = (new PredicateLowering())->lower($statement->where, $binding);
        $target = $binding->source();
        Check::input($target !== null, 'A complete DELETE requires a resolved target.');

        return new Delete($target, $where, $profile->grammar);
    }
}
