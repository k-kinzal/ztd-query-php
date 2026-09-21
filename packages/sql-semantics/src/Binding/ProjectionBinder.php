<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds output expressions and expands stars in declaration order.
 *
 * @visibility SqlSemantics
 */
final class ProjectionBinder
{
    /**
     * @return list<OutputColumn>
     */
    public function bind(Node $select, Scope $scope): array
    {
        $items = QueryNodes::local($select, ['target_el', 'select_item']);
        if ($scope->identifiers->dialect === Dialect::Sqlite) {
            $items = array_reverse((new Query\SqliteLists())->projection($select));
        }
        if ($items === []) {
            $children = Tree::significant($select);
            if ($children !== [] && strtoupper(Tree::text($children[0])) === 'TABLE') {
                return $this->star([], $scope, $select, 0);
            }
            if ($scope->identifiers->dialect === Dialect::PostgreSql && Tree::outer($select, ['opt_target_list']) !== []) {
                return [];
            }
            $list = QueryNodes::local($select, ['select_item_list'])[0] ?? null;
            if ($list !== null && Tree::text($list) === '*') {
                return $this->star([], $scope, $list, 0);
            }
            Tree::invalid($select, 'empty projection');
        }
        $outputs = [];
        foreach ($items as $item) {
            array_push($outputs, ...$this->item($item, $scope, count($outputs)));
        }

        return $outputs;
    }

    /**
     * @return list<OutputColumn>
     */
    public function item(Node $item, Scope $scope, int $ordinal): array
    {
        $expression = Tree::child($item, ['a_expr', 'expr', 'table_wild']);
        $aliasNode = Tree::child($item, ['ColLabel', 'BareColLabel', 'select_alias', 'as']);
        $tokens = $expression === null ? $item->tokens() : $expression->tokens();
        if ($expression === null && $scope->identifiers->dialect === Dialect::Sqlite) {
            $tokens = [];
            foreach (Tree::significant($item) as $child) {
                if ($child instanceof Node && in_array($child->name, ['sclp', 'as'], true)) {
                    continue;
                }
                array_push($tokens, ...($child instanceof Node ? $child->tokens() : [$child]));
            }
        }
        if ($tokens !== [] && $tokens[count($tokens) - 1]->text === '*') {
            $parts = [];
            foreach (array_slice($tokens, 0, -1) as $token) {
                if ($token->text !== '.') {
                    $parts[] = $scope->identifiers->name($token);
                }
            }
            return $this->star($parts, $scope, $item, $ordinal);
        }
        if ($expression === null) {
            Tree::invalid($item, 'projection');
        }
        $bound = (new ExpressionBinder())->bind($expression, $scope);
        if ($scope->identifiers->dialect === Dialect::PostgreSql && $bound->kind === ExpressionKind::Literal && $bound->type->name === 'unknown') {
            $bound = (new ExpressionRules(Dialect::PostgreSql))->coerce($bound, new TypeDescriptor(Dialect::PostgreSql, 'text'));
        }
        $alias = null;
        if ($aliasNode !== null) {
            $aliasTokens = $aliasNode->tokens();
            $alias = $scope->identifiers->name($aliasTokens[count($aliasTokens) - 1]);
        }

        return [new OutputColumn($ordinal, $alias ?? $bound->binding?->column->name ?? ($bound->reference[count($bound->reference) - 1] ?? null), $bound)];
    }

    /**
     * @param list<string> $qualifiers
     * @return list<OutputColumn>
     * @throws SemanticException
     */
    public function star(array $qualifiers, Scope $scope, Node $source, int $ordinal): array
    {
        $outputs = [];
        if ($qualifiers === []) {
            foreach ($scope->merged as $name => $expression) {
                $outputs[] = new OutputColumn($ordinal + count($outputs), $name, $expression);
            }
        }
        foreach ($scope->relations as $relation) {
            if (!$scope->matches($relation, $qualifiers)) {
                continue;
            }
            if (!$relation->declaration->resolved) {
                $outputs[] = new OutputColumn($ordinal + count($outputs), null, new \SqlSemantics\Model\Expression(ExpressionKind::Wildcard, new TypeDescriptor($scope->identifiers->dialect, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, $source, reference: [$relation->alias ?? $relation->declaration->name]));
            }
            foreach ($relation->declaration->columns as $column) {
                if ($qualifiers === [] && isset($scope->merged[$column->name])) {
                    continue;
                }
                $bound = $scope->column([$relation->alias ?? $relation->declaration->name, $column->name], $source);
                $outputs[] = new OutputColumn($ordinal + count($outputs), $column->name, $bound);
            }
        }
        if ($outputs === []) {
            $scope->diagnostics()->report('unknown-relation', 'Star has no matching relation.', $source);
            $outputs[] = new OutputColumn($ordinal, null, new \SqlSemantics\Model\Expression(ExpressionKind::Wildcard, new TypeDescriptor($scope->identifiers->dialect, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, $source, reference: $qualifiers));
        }

        return $outputs;
    }
}
