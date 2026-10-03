<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use ReflectionObject;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Table;
use UnitEnum;

/**
 * Protects named alias targets when an enclosing projection is persistently edited.
 * @visibility SqlSemantics
 */
final class AliasDependencies
{
    /**
     * Finds actual alias dependencies without treating the whole visible namespace as used.
     * @return list<NamedAlias>
     */
    public function references(ScalarExpression $expression): array
    {
        $pending = [$expression];
        $seen = [];
        $aliases = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            $id = spl_object_id($value);
            if (isset($seen[$id]) || $value instanceof UnitEnum || $value instanceof Scope || $value instanceof SqliteAliasScope || $value instanceof Catalog || $value instanceof TableReference || $value instanceof Table || $value instanceof Column) {
                continue;
            }
            $seen[$id] = true;
            if ($value instanceof NamedAlias) {
                $aliases[] = $value;
            }
            if ($value instanceof NamedAlias || $value instanceof AliasReference) {
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
        return $aliases;
    }

    /**
     * Preserves the first matching alias and its exact field within the edited projection.
     */
    public function preserved(ScalarExpression $expression, Fields $projection): bool
    {
        foreach ($this->references($expression) as $reference) {
            if ($reference->projection->scope === $projection->scope && ($projection->matchingAliases($reference->name->value)[0] ?? null) !== $reference->field) {
                return false;
            }
        }
        return true;
    }
}
