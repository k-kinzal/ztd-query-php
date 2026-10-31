<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Locking;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Finds contextualized query scopes through lexical common-table references.
 *
 * Unused definitions retain grammar notices but do not contextualize locks.
 * This temporary walk shares no parser nodes with a published semantic value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReachedScopes
{
    /**
     * @return list<array{Node, list<Name>}> Reached query closings with their visible CTE names
     */
    public function read(Node $root, Lowering $lowering): array
    {
        /**
 * @var list<array<string|int, int>> $scopes
 */
        $scopes = [[]];
        /**
 * @var list<array{Node, int, bool}> $definitions
 */
        $definitions = [];
        /**
 * @var list<array{Node, int, bool}> $pending
 */
        $pending = [[$root, 0, false]];
        $seen = [];
        $closed = [];
        while ($pending !== []) {
            [$node, $scope, $done] = array_pop($pending);
            if ($done) {
                if (in_array($node->name, ['select_stmt', 'select_stmt_with_into', 'query_expression_with_opt_locking_clauses'], true)) {
                    $closed[] = [$node, array_map(static fn (string|int $name): Name => new Name((string) $name), array_keys($scopes[$scope]))];
                }
                continue;
            }
            $key = spl_object_id($node) . ':' . $scope;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($node->name === 'query_expression' && $node->children[0]->name === 'with_clause') {
                $scope = $this->extend($node, $lowering, $scope, $scopes, $definitions);
            }
            $pending[] = [$node, $scope, true];
            if (in_array($node->name, ['single_table', 'explicit_table'], true)) {
                $name = $lowering->names->qualified($lowering->form($node)->node($node->name === 'single_table' ? 0 : 1));
                $reference = $name->schema === null ? ($scopes[$scope][$name->name->value] ?? null) : null;
                if ($reference !== null) {
                    $pending[] = $definitions[$reference];
                }
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof Node && $child->name !== 'with_clause') {
                    $pending[] = [$child, $scope, false];
                }
            }
        }
        return $closed;
    }
    /**
     * Adds definitions in lexical order; only RECURSIVE exposes a definition to itself.
     *
     * @param list<array<string|int, int>> $scopes The temporary lexical environments
     * @param list<array{Node, int, bool}> $definitions CTE bodies with their defining scope
     */
    public function extend(Node $node, Lowering $lowering, int $scope, array &$scopes, array &$definitions): int
    {
        $bindings = $scopes[$scope];
        $with = $lowering->form($lowering->form($node)->node(0));
        foreach ((new Lists())->items($with->node(count($with->node->children) - 1)) as $table) {
            $form = $lowering->form($table);
            $id = count($definitions);
            $previous = $bindings;
            $bindings[$lowering->names->identifier($form->node(0))->value] = $id;
            $definitions[] = [$form->node(3), count($scopes), false];
            $scopes[] = count($with->node->children) === 3 ? $bindings : $previous;
        }
        $scope = count($scopes);
        $scopes[] = $bindings;
        return $scope;
    }

}
