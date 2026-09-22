<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\TokenGroups;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\JoinKind;

/**
 * Binds USING and NATURAL names without replacing their matching operation with ON.
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
        $rules = new ExpressionRules($left->scope->identifiers->dialect, $left->scope->diagnostics());
        $columns = [];
        $merged = [];
        foreach ($names as $name) {
            $name = (string) $name;
            $a = $left->scope->column([$name], $source);
            $b = $right->scope->column([$name], $source);
            $merged[$name] = match ($kind) {
                JoinKind::Right => $b,
                JoinKind::Full => $rules->call('COALESCE', [$a, $b], $source),
                JoinKind::Cross, JoinKind::Inner, JoinKind::Left => $a,
            };
            $columns[] = new \SqlSemantics\Model\Relation\Joining\SharedColumn($name, $a, $b, $merged[$name]);
        }
        $leftScope = in_array($kind, [JoinKind::Right, JoinKind::Full], true) ? $left->scope->extend($id) : $left->scope;
        $rightScope = in_array($kind, [JoinKind::Left, JoinKind::Full], true) ? $right->scope->extend($id) : $right->scope;
        $scope = $leftScope->combine($rightScope, $source);
        $scope = new Scope($scope->identifiers, $scope->relations, $scope->extensions, $scope->parent, $scope->queries, $merged);
        $relation = $using === null
            ? new \SqlSemantics\Model\Relation\Joining\NaturalJoin($id, $kind, $left->relation, $right->relation, $columns, $source)
            : new \SqlSemantics\Model\Relation\Joining\UsingJoin($id, $kind, $left->relation, $right->relation, $columns, $source);
        return new BoundRelation($relation, $scope);
    }
}
