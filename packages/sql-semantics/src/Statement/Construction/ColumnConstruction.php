<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Failure\ImplementationGap;

/**
 * Applies the fixed profile's reference and truth-name rules to a new use site.
 * @visibility SqlSemantics
 */
final class ColumnConstruction
{
    /**
     * Alias and outer lookups remain tied to the declarations actually visible here.
     * @throws ImplementationGap
     */
    public function derive(Expression\ColumnUse $input, Scope|SqliteAliasScope $scope): ColumnReference|BooleanReference|AliasReference|ColumnOrAlias
    {
        $column = new ColumnReference($scope, $input->name, $input->qualifier);
        if ($column->resolution instanceof NamedAlias && $scope instanceof SqliteAliasScope && $column->resolution->projection === $scope->projection) {
            return new AliasReference($scope->projection, $column->resolution->field, $column->name);
        }
        if ($column->resolution instanceof CandidateColumn && $scope instanceof SqliteAliasScope) {
            foreach ($column->resolution->possibilities as $candidate) {
                if ($candidate instanceof NamedAlias && $candidate->projection === $scope->projection) {
                    return new ColumnOrAlias($column, new AliasReference($scope->projection, $candidate->field, $column->name));
                }
            }
        }
        if ($column->qualifier === null && !$column->resolution instanceof ResolvedColumn && !$column->resolution instanceof NamedAlias && $column->name->quote === Quote::Double) {
            throw new ImplementationGap('The selected profile requires the unresolved double-quoted identifier/string rule.');
        }
        return $column->qualifier === null && $column->name->quote === Quote::None && in_array(strtoupper($column->name->value), ['TRUE', 'FALSE'], true)
            ? new BooleanReference($column)
            : $column;
    }
}
