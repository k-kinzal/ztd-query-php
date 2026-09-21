<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\TokenGroups;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\JoinKind;

/**
 * Expands USING and NATURAL into equality predicates and merged output columns.
 *
 * @visibility SqlSemantics
 */
final class UsingJoin
{
    /**
     * Resolves shared names in SQL output order before applying NULL extension.
     */
    public function bind(BoundRelation $left, BoundRelation $right, JoinKind $kind, Node $source, string $id, ?Node $using): BoundRelation
    {
        $leftColumns = $left->scope->outputColumns($source);
        $rightColumns = $right->scope->outputColumns($source);
        $names = $using === null ? array_values(array_intersect(array_keys($leftColumns), array_keys($rightColumns))) : TokenGroups::names(TokenGroups::parentheses($using->tokens())[0] ?? [], $left->scope->identifiers);
        $rules = new ExpressionRules($left->scope->identifiers->dialect);
        $predicate = null;
        $merged = [];
        foreach ($names as $name) {
            $a = $left->scope->column([$name], $source);
            $b = $right->scope->column([$name], $source);
            $comparison = $rules->operator('=', [$a, $b], $source);
            $predicate = $predicate === null ? $comparison : $rules->operator('AND', [$predicate, $comparison], $source);
            $merged[$name] = match ($kind) {
                JoinKind::Right => $b,
                JoinKind::Full => $rules->call('COALESCE', [$a, $b], $source),
                JoinKind::Cross, JoinKind::Inner, JoinKind::Left => $a,
            };
        }
        $leftScope = in_array($kind, [JoinKind::Right, JoinKind::Full], true) ? $left->scope->extend($id) : $left->scope;
        $rightScope = in_array($kind, [JoinKind::Left, JoinKind::Full], true) ? $right->scope->extend($id) : $right->scope;
        $scope = $leftScope->combine($rightScope, $source);
        $scope = new Scope($scope->identifiers, $scope->relations, $scope->extensions, $scope->parent, $scope->queries, $merged);
        return new BoundRelation(new Join($id, $kind, $left->relation, $right->relation, $predicate, $source), $scope);
    }
}
