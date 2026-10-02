<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Construction\Subquery as C;
use SqlSemantics\Statement\Expression\Subquery as E;
use SqlSemantics\Statement\Query\ScopedRows;
use SqlSemantics\Statement\Query\ScopedSelect;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Check;

/**
 * Checks the nested query's actual lexical owner and the enclosing evaluation operation.
 * @visibility SqlSemantics
 */
final class SubqueryMatch
{
    /**
     * Existence, scalar selection, and membership cannot substitute for one another.
     * @return list<ScalarPair>
     */
    public function children(ScalarPair $pair): array
    {
        $input = $pair->input;
        $actual = $pair->actual;
        $children = [];
        if ($input instanceof C\ScalarQueryInput) {
            Check::invariant($actual instanceof E\SqliteScalarSubquery, 'A scalar query must retain scalar-selection semantics.');
        } elseif ($input instanceof C\ExistsInput) {
            Check::invariant($actual instanceof E\SqliteExists, 'An existence test must retain existence semantics.');
        } else {
            Check::invariant($input instanceof C\InQueryInput && $actual instanceof E\SqliteInQuery && $input->negated === $actual->negated, 'Query membership must retain its actual operation and polarity.');
            $children[] = new ScalarPair($input->subject, $actual->subject, $pair->scope);
        }
        $scope = $pair->scope instanceof SqliteAliasScope ? $pair->scope->scope : $pair->scope;
        Check::invariant($actual->source->scope === $scope, 'A nested query must remain at its actual expression site.');
        $query = $actual->source->query;
        if ($input->query instanceof SelectDefinition) {
            Check::invariant($query instanceof ScopedSelect, 'A nested SELECT cannot become a VALUES request.');
            (new QueryMatch())->select($pair->scope, $input->query, $query);
        } else {
            Check::invariant($query instanceof ScopedRows, 'Nested VALUES cannot become a SELECT request.');
            (new QueryMatch())->rows($pair->scope, $input->query, $query);
        }
        return $children;
    }
}
