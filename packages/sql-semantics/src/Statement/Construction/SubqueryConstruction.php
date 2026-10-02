<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;

/**
 * Builds a nested query from new inputs in its immediate lexical environment.
 * @visibility SqlSemantics
 */
final class SubqueryConstruction
{
    /**
     * The input never contains a bound query or a former parent scope.
     */
    public function derive(Query\SelectDefinition|Query\RowsDefinition $input, Scope|SqliteAliasScope $context): SqliteSubquery
    {
        $query = $input instanceof Query\SelectDefinition
            ? new ScopedSelect($context, $input)
            : (new RowsConstruction())->derive($input, $context);
        return new SqliteSubquery($context instanceof SqliteAliasScope ? $context->scope : $context, $query);
    }
}
