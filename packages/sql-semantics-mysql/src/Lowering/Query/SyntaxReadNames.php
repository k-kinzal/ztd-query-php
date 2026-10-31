<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers relation identities independently of later refused expression operands.
 * Parser nodes remain transient; only validated names leave this input reader.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxReadNames
{
    /**
     * Follows reached common-table definitions in their lexical scopes.
     *
     * @param list<Node> $roots Relation or query inputs
     * @return list<QualifiedName> Physical relation names completed before the refusal
     */
    public function names(array $roots, Lowering $lowering, Comparison $comparison, int $end, ?Node $with = null): array
    {
        $scopes = [[]];
        $definitions = [];
        $initial = $with === null ? 0 : $this->bind($with, $lowering, $comparison, $scopes, $definitions, 0);
        $pending = array_map(static fn (Node $node): array => [$node, $initial], array_reverse($roots));
        $seen = [];
        $names = [];
        while ($pending !== []) {
            [$node, $scope] = array_pop($pending);
            $key = spl_object_id($node) . ':' . $scope;
            if (isset($seen[$key]) || in_array($node->name, ['with_clause', 'opt_with_clause'], true)) {
                continue;
            }
            $seen[$key] = true;
            if ($node->name === 'table_ident') {
                if (((new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? PHP_INT_MAX) > $end) {
                    continue;
                }
                $name = $lowering->names->qualified($node);
                $binding = $name->schema === null ? ($scopes[$scope][$comparison->fold($name->name->value)] ?? null) : null;
                if ($binding !== null) {
                    $pending[] = $definitions[$binding];
                } elseif ((new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->problem($name) === null) {
                    $names[] = $name;
                }
                continue;
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node && in_array($child->name, ['with_clause', 'opt_with_clause'], true)) {
                    $scope = $this->bind($child, $lowering, $comparison, $scopes, $definitions, $scope);
                }
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = [$child, $scope];
                }
            }
        }
        return $names;
    }

    /**
     * Assigns each definition the preceding bindings and its recursive self binding.
     *
     * @param list<array<string, int>> $scopes Lexical name environments
     * @param list<array{Node, int}> $definitions Transient definition bodies and their environments
     */
    public function bind(Node $clause, Lowering $lowering, Comparison $comparison, array &$scopes, array &$definitions, int $outer): int
    {
        $with = $clause->name === 'with_clause' ? $clause : ($clause->find('with_clause')[0] ?? null);
        if ($with === null) {
            return $outer;
        }
        $form = $lowering->form($with);
        $recursive = str_contains($form->signature, 'RECURSIVE_SYM');
        $list = $form->node($recursive ? 2 : 1);
        $bindings = $scopes[$outer];
        foreach ((new \SqlSemantics\Lowering\Lists())->items($list) as $item) {
            $definition = $lowering->form($item);
            $id = count($definitions);
            $scope = count($scopes);
            $previous = $bindings;
            $bindings[$comparison->fold($lowering->names->identifier($definition->node(0))->value)] = $id;
            $scopes[] = $recursive ? $bindings : $previous;
            $definitions[] = [$definition->node(3), $scope];
        }
        $scope = count($scopes);
        $scopes[] = $bindings;
        return $scope;
    }
}
