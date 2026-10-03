<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Reference;

use ReflectionObject;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Expression\Subquery\SqliteSubquery;
use SqlSemantics\Statement\Relation\Scope;
use UnitEnum;

/**
 * Checks column ownership at actual query boundaries, not merely lexical ancestry.
 * @visibility SqlSemantics
 */
final class Ownership
{
    /**
     * A nested lookup is legal only inside the subquery that owns its namespace.
     */
    public function accepts(ScalarExpression $expression, Scope $scope): bool
    {
        $pending = [$expression];
        $seen = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            $id = spl_object_id($value);
            if (isset($seen[$id]) || $value instanceof UnitEnum) {
                continue;
            }
            $seen[$id] = true;
            if ($value instanceof ColumnReference || $value instanceof SqliteSubquery) {
                if ($value->scope !== $scope) {
                    return false;
                }
                continue;
            }
            if ($value instanceof \SqlSemantics\Statement\Projection\AliasReference) {
                $pending[] = $value->field->expression;
                continue;
            }
            foreach ((new ReflectionObject($value))->getProperties() as $property) {
                $field = $property->getValue($value);
                foreach (is_array($field) ? $field : [$field] as $member) {
                    if (is_object($member)) {
                        $pending[] = $member;
                    }
                }
            }
        }
        return true;
    }
}
