<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use ReflectionObject;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Table;
use UnitEnum;

/**
 * Audits the semantic result independently of SQL reconstruction.
 * @visibility SqlSemantics
 */
final class SemanticGraph
{
    /**
     * Requires an operation whose reachable values are immutable and independent of syntax.
     */
    public function isSemanticOperation(object $root): bool
    {
        return $root instanceof Operation && $this->containsOnlyValues($root);
    }

    /**
     * Rejects grammar models, parser objects, mutable properties, and hidden foreign state.
     */
    public function containsOnlyValues(object $root): bool
    {
        return (new Validation\ValueGraph())->accepts($root);
    }

    /**
     * Compares semantic values and entity references, independently of alias-substitution spelling.
     */
    public function fingerprint(Operation $operation): string
    {
        Validation\Check::invariant($this->isSemanticOperation($operation), 'An operation must retain only immutable semantic values.');
        $entities = [];
        return $this->describe($operation, $entities);
    }

    /**
     * Describes values structurally and declarations/occurrences by their positions in the graph.
     * @param array<int, int> $entities
     */
    public function describe(object $value, array &$entities): string
    {
        if ($value instanceof Expression\Rendering\GroupedExpression) {
            return $this->describe($value->operand, $entities);
        }
        if ($value instanceof SqliteAliasScope) {
            return $this->describe($value->scope, $entities);
        }
        if ($value instanceof AliasReference) {
            return $this->describe($value->field->expression, $entities);
        }
        if ($value instanceof Field) {
            return serialize([Field::class, $value->name->value, $this->describe($value->expression, $entities)]);
        }
        if ($value instanceof UnitEnum) {
            return serialize($value);
        }
        $entity = $value instanceof Catalog || $value instanceof Scope || $value instanceof TableReference || $value instanceof Table || $value instanceof Column;
        $id = spl_object_id($value);
        if ($entity && isset($entities[$id])) {
            return serialize(['reference', $entities[$id]]);
        }
        $position = $entity ? count($entities) : null;
        if ($position !== null) {
            $entities[$id] = $position;
        }
        $properties = [];
        foreach ((new ReflectionObject($value))->getProperties() as $property) {
            if ($value instanceof Expression\SqliteBinary && $property->getName() === 'layout') {
                continue;
            }
            $field = $property->getValue($value);
            $members = [];
            foreach (is_array($field) ? $field : [$field] as $key => $member) {
                $members[$key] = is_object($member) ? $this->describe($member, $entities) : serialize($member);
            }
            $properties[$property->getName()] = [is_array($field), $members];
        }
        return serialize([$value::class, $position, $properties]);
    }

    /**
     * Finds alias alternatives that must remain visible until their missing declarations are known.
     * @return list<ColumnOrAlias>
     */
    public function conditionalAliases(ScalarExpression $expression): array
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
            if ($value instanceof ScalarExpression && $value->references() === []) {
                continue;
            }
            if ($value instanceof ColumnOrAlias) {
                $aliases[] = $value;
            }
            if ($value instanceof AliasReference) {
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
}
