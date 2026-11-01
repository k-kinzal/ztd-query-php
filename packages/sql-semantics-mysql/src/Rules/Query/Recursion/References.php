<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Recursion;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Locates self references through lexical CTE scopes and query-block boundaries.
 *
 * Only reached definitions are expanded. Inner definitions can shadow the name,
 * and qualified references always address the catalog instead of the CTE.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class References
{
    /**
     * @var list<array<string, int>> Lexical bindings; -1 denotes the requested name
     */
    public array $scopes = [];
    /**
     * @var list<array{M\With\CommonTableExpression, int}> Definition and declaration scope
     */
    public array $definitions = [];
    /**
     * @var list<array{int, int}> Query block identity and subquery depth
     */
    public array $references = [];

    /**
     * @return list<array{int, int}> Each reference's owning block and nesting depth
     */
    public function of(Query $query, Name $name, Comparison $names = Comparison::Sensitive): array
    {
        $this->scopes = [[$names->fold($name->value) => -1]];
        $this->definitions = [];
        $this->references = [];
        $this->visit($query, 0, 0, 0, [], $names);
        return $this->references;
    }

    /**
     * Traverses owned operands and active definitions without revisiting a CTE cycle.
     *
     * @param list<int> $active Definitions already being expanded on this path
     */
    public function visit(Node $node, int $scope, int $depth, int $block, array $active, Comparison $names): void
    {
        if ($node instanceof M\QueryExpression && $node->with instanceof M\With\With) {
            $scope = $this->scope($node->with, $scope, $names);
        }
        if ($node instanceof M\Select || $node instanceof M\ValuesQuery || $node instanceof M\ExplicitTable || $node instanceof M\With\DeferredBody || $node instanceof M\With\DeferredValuesQuery) {
            $block = spl_object_id($node);
        }
        if (($node instanceof TableReference || $node instanceof M\ExplicitTable) && $node->name()->schema === null) {
            $binding = $this->scopes[$scope][$names->fold($node->name()->name->value)] ?? null;
            if ($binding === -1) {
                $this->references[] = [$block, $depth];
            } elseif ($binding !== null && !in_array($binding, $active, true)) {
                \SqlSemantics\Diagnostic\Check::invariant(isset($this->definitions[$binding]), 'A lexical CTE binding names a registered definition.');
                [$definition, $outer] = $this->definitions[$binding];
                $this->visit($definition->query, $outer, $depth + 1, $block, [...$active, $binding], $names);
            }
        }
        foreach ((new ReadDependencies())->children($node) as $child) {
            $nested = $child instanceof Query && !$this->wrapper($node);
            $this->visit($child, $scope, $depth + (int) $nested, $block, $active, $names);
        }
    }

    /**
     * Extends a query's scope in declaration order, preserving each definition's scope.
     */
    public function scope(M\With\With $with, int $outer, Comparison $names): int
    {
        $bindings = $this->scopes[$outer];
        foreach ($with->tables as $table) {
            $previous = $bindings;
            $bindings[$names->fold($table->name->value)] = count($this->definitions);
            $this->definitions[] = [$table, count($this->scopes)];
            $this->scopes[] = $with->recursive ? $bindings : $previous;
        }
        $scope = count($this->scopes);
        $this->scopes[] = $bindings;
        return $scope;
    }

    /**
     * Tells whether child queries retain the surrounding set-expression depth.
     */
    public function wrapper(Node $node): bool
    {
        return $node instanceof M\QueryExpression || $node instanceof M\ParenthesizedQuery || $node instanceof M\QueryStatement || $node instanceof M\Set\SetOperation || $node instanceof M\Set\LeadingUnion || $node instanceof M\Set\OrderedSetOperation;
    }
}
