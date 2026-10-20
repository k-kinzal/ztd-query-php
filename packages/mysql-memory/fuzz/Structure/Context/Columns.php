<?php

declare(strict_types=1);

namespace Fuzz\Structure\Context;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies the relation namespace of a standalone scalar without replacing it.
 * Subqueries retain their own namespace. Unqualified references share the first
 * relation; qualified references keep their requested qualifier and database.
 */
final class Columns
{
    /**
     * @return list<ColumnUse> Free column references, stopping at nested queries
     */
    public function uses(Scalar $root): array
    {
        $found = [];
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if ($value instanceof Query) {
                continue;
            }
            if ($value instanceof ColumnUse) {
                $found[] = $value;
            } elseif (is_array($value)) {
                array_push($pending, ...array_reverse(array_values($value)));
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_reverse(array_values(get_object_vars($value))));
            }
        }
        return $found;
    }

    /**
     * @return list<array{relation: TableReference, columns: non-empty-list<Name>}> Fixture identities and their columns
     */
    public function bindings(Scalar $root): array
    {
        $uses = $this->uses($root);
        $groups = [];
        foreach ($uses as $use) {
            if ($use->qualifier !== null) {
                $key = serialize([$use->qualifier->schema?->value, $use->qualifier->name->value]);
                $group = $groups[$key] ?? ['relation' => $this->relation($use->qualifier, count($groups)), 'columns' => []];
                $group['columns'][mb_strtolower($use->name->value, 'UTF-8')] = $use->name;
                $groups[$key] = $group;
            }
        }
        foreach ($uses as $use) {
            if ($use->qualifier === null) {
                $key = array_key_first($groups) ?? 'unqualified';
                $group = $groups[$key] ?? ['relation' => $this->relation(null, 0), 'columns' => []];
                $group['columns'][mb_strtolower($use->name->value, 'UTF-8')] = $use->name;
                $groups[$key] = $group;
            }
        }
        return array_values(array_map(static fn (array $group): array => ['relation' => $group['relation'], 'columns' => array_values($group['columns'])], $groups));
    }

    /**
     * Creates physical identities for fixture state while preserving aliases.
     */
    public function relation(?QualifiedName $qualifier, int $index): TableReference
    {
        if ($qualifier?->schema !== null) {
            return new TableReference($qualifier);
        }
        return new TableReference(new QualifiedName(new Name('input_' . $index), new Name('structure_fixture')), $qualifier?->name);
    }

    /**
     * Builds only the ambient FROM clause of a scalar's containing query.
     */
    public function input(Scalar $root): ?TableSource
    {
        $tables = array_column($this->bindings($root), 'relation');
        return match (count($tables)) {
            0 => null,
            1 => $tables[0],
            default => new TableList($tables),
        };
    }
}
