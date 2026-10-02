<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\ColumnConstruction;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Validation\Check;

/**
 * Matches a new name use to its actual lookup and concrete expression interpretation.
 * @visibility SqlSemantics
 */
final class ColumnMatch
{
    /**
     * Resolution primitives are shared; the actual operand and its pointers are checked independently.
     */
    public function check(ColumnUse $input, ScalarExpression $actual, Scope|SqliteAliasScope $scope): void
    {
        $expected = (new ColumnConstruction())->derive($input, $scope);
        if ($expected instanceof AliasReference) {
            Check::invariant($actual instanceof AliasReference, 'A resolved alias must retain the alias-use operation.');
            $this->alias($expected, $actual);
        } elseif ($expected instanceof ColumnOrAlias) {
            Check::invariant($actual instanceof ColumnOrAlias, 'A conditional alias must retain both lookup alternatives.');
            $this->reference($expected->column, $actual->column);
            $this->alias($expected->alias, $actual->alias);
        } elseif ($expected instanceof BooleanReference) {
            Check::invariant($actual instanceof BooleanReference, 'A truth name must retain its literal alternative.');
            $this->reference($expected->column, $actual->column);
        } else {
            Check::invariant($actual instanceof ColumnReference, 'A column request must retain its actual column-use operand.');
            $this->reference($expected, $actual);
        }
    }

    /**
     * The use environment, qualified name, and all resolution alternatives must correspond.
     */
    public function reference(ColumnReference $expected, ColumnReference $actual): void
    {
        Check::invariant($expected->scope === $actual->scope && NamesMatch::same($expected->name, $actual->name) && NamesMatch::qualified($expected->qualifier, $actual->qualifier), 'A column operand must belong to its actual use site and name.');
        (new ResolutionMatch())->check($expected->resolution, $actual->resolution);
    }

    /**
     * Even equal expression values in a different projection are not the same alias target.
     */
    public function alias(AliasReference $expected, AliasReference $actual): void
    {
        Check::invariant($expected->projection === $actual->projection && $expected->field === $actual->field && NamesMatch::same($expected->name, $actual->name), 'An alias operand must name the exact requested output field.');
    }
}
