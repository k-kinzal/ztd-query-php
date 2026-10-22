<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Visits reached inputs with their lexical columns and read-position meaning.
 * Unused CTE definitions remain templates. EXISTS suppresses expansion only in
 * its own projection; derived inputs and nested value queries retain read roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryScopes
{
    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     * @return list<array{Query, array<string, list<Name|null>|null>, bool}> Queries, bindings and EXISTS roles
     */
    public function of(Node $root, bool $exists = false, array $common = []): array
    {
        $queries = [];
        foreach ($this->nodes($root, $exists, $common) as [$node, $scope, $role]) {
            if ($node instanceof Query) {
                $queries[] = [$node, $scope, $role];
            }
        }
        return $queries;
    }

    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     * @return list<array{Node, array<string, list<Name|null>|null>, bool}> Reached occurrences in their original scopes
     */
    public function nodes(Node $root, bool $exists = false, array $common = []): array
    {
        return $this->inputs([$root], null, $exists, $common);
    }

    /**
     * @param list<Node> $roots Input occurrences sharing an enclosing WITH
     * @param array<string, list<Name|null>|null> $common Outer structural bindings
     * @return list<array{Node, array<string, list<Name|null>|null>, bool}> Reached scoped inputs
     */
    public function inputs(array $roots, ?M\With\With $with = null, bool $exists = false, array $common = []): array
    {
        $reached = array_fill_keys(array_map(static fn (Node $node): int => spl_object_id($node), (new ReadDependencies())->inputs($roots, $with)), true);
        [$found, $common] = $this->bindings($with, $common, $reached);
        foreach ($roots as $root) {
            array_push($found, ...$this->walk($root, $common, $reached, $exists));
        }
        return $found;
    }

    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     * @param array<int, true> $reached Definitions reached through lexical references
     * @return list<array{Node, array<string, list<Name|null>|null>, bool}> Inputs with their original scopes and roles
     */
    public function walk(Node $node, array $common, array $reached, bool $exists = false): array
    {
        $found = [[$node, $common, $exists]];
        $dependencies = new ReadDependencies();
        [$definitions, $common] = $this->bindings($dependencies->with($node), $common, $reached);
        array_push($found, ...$definitions);
        foreach ($dependencies->children($node) as $child) {
            array_push($found, ...$this->walk($child, $common, $reached, $this->projection($node, $exists)));
        }
        return $found;
    }

    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     * @param array<int, true> $reached Activated definition identities
     * @return array{list<array{Node, array<string, list<Name|null>|null>, bool}>, array<string, list<Name|null>|null>} Reached definitions and final body bindings
     */
    public function bindings(?M\With\With $with, array $common, array $reached): array
    {
        $found = [];
        foreach ($with->tables ?? [] as $table) {
            $scope = $common;
            if ($with?->recursive === true) {
                $scope[$table->name->value] = null;
            }
            $columns = $table->columns !== [] ? $table->columns : (new QueryColumns())->of($table->query, common: $scope);
            if ($with?->recursive === true) {
                $scope[$table->name->value] = $columns;
            }
            if (isset($reached[spl_object_id($table)])) {
                array_push($found, ...$this->walk($table, $scope, $reached));
            }
            $common[$table->name->value] = $columns;
        }
        return [$found, $common];
    }

    /**
     * Carries the EXISTS role through query wrappers and set operands only.
     */
    public function projection(Node $node, bool $exists): bool
    {
        return $node instanceof Exists || ($exists && ($node instanceof M\QueryExpression || $node instanceof M\ParenthesizedQuery || $node instanceof M\QueryStatement || $node instanceof M\Set\SetOperation || $node instanceof M\Set\OrderedSetOperation || $node instanceof M\Set\LeadingUnion));
    }
}
