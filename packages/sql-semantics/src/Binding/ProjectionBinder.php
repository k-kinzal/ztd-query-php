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
     * @throws \SqlSemantics\InvalidSql
     */
    public function bind(Node $select, Scope $scope): array
    {
        if ($select->name === 'derived_table_list') {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::QueryOperand, $select);
        }
        $items = QueryNodes::local($select, ['target_el', 'select_item']);
        $mysqlList = QueryNodes::local($select, ['select_item_list'])[0] ?? null;
        if ($mysqlList !== null) {
            $items = $this->mysqlItems($mysqlList);
        }
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
            Tree::invalid($select, 'empty projection');
        }
        $outputs = [];
        foreach ($items as $item) {
            array_push($outputs, ...$this->item($item, $scope, count($outputs)));
        }

        return $outputs;
    }

    /**
     * Keeps a leading unqualified star alongside subsequent MySQL projection items.
     *
     * @return list<Node>
     */
    public function mysqlItems(Node $node): array
    {
        if ($node->name === 'select_item' || Tree::text($node) === '*') {
            return [$node];
        }
        $items = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['select_item_list', 'select_item'], true)) {
                array_push($items, ...$this->mysqlItems($child));
            }
        }
        return $items;
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
        if (self::starred($tokens, $expression)) {
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
            $bound = (new ExpressionRules(Dialect::PostgreSql))->coerce($bound, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        }
        $alias = null;
        if ($aliasNode !== null) {
            $aliasTokens = $aliasNode->tokens();
            $alias = $scope->identifiers->name($aliasTokens[count($aliasTokens) - 1]);
        }

        return [new OutputColumn($ordinal, $alias ?? $bound->columnBinding()?->column->name ?? ($bound->referenceParts()[count($bound->referenceParts()) - 1] ?? null), $bound)];
    }

    /**
     * @param list<string> $qualifiers
     * @return list<OutputColumn>
     * @throws SemanticException
     */
    public function star(array $qualifiers, Scope $scope, Node $source, int $ordinal): array
    {
        if ($qualifiers === [] && $scope->outputs !== null) {
            return array_map(static fn (OutputColumn $column): OutputColumn => new OutputColumn($ordinal + $column->ordinal, $column->name, $column->expression), $scope->outputs);
        }
        $outputs = [];
        if ($qualifiers === []) {
            foreach ($scope->merged as $name => $expression) {
                $outputs[] = new OutputColumn($ordinal + count($outputs), (string) $name, $expression);
            }
        }
        foreach ($scope->relations as $relation) {
            if (!$scope->matches($relation, $qualifiers)) {
                continue;
            }
            if (!$relation->declaration->resolved) {
                $outputs[] = new OutputColumn($ordinal + count($outputs), null, new \SqlSemantics\Model\Scalar\Reference\Wildcard(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, []), $source, [$relation->alias ?? $relation->declaration->name]));
            }
            $names = array_map(static fn (\SqlSemantics\Schema\ColumnDefinition $column): string => $column->name, $relation->declaration->columns);
            $positional = count(array_unique($names)) !== count($names);
            foreach ($relation->declaration->columns as $position => $column) {
                if ($qualifiers === [] && isset($scope->merged[$column->name])) {
                    continue;
                }
                $parts = [$relation->alias ?? $relation->declaration->name, $column->name];
                $bound = $positional ? $scope->reference($relation, $position, $parts, $source) : $scope->column($parts, $source);
                $outputs[] = new OutputColumn($ordinal + count($outputs), $column->name, $bound);
            }
        }
        if ($outputs === []) {
            $scope->diagnostics()->report('unknown-relation', 'Star has no matching relation.', $source);
            $outputs[] = new OutputColumn($ordinal, null, new \SqlSemantics\Model\Scalar\Reference\Wildcard(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, []), $source, $qualifiers));
        }

        return $outputs;
    }

    /**
     * Whether a select item is a star over relations, not the expansion of a composite value.
     * @param list<\SqlParser\Lexer\Token> $tokens
     */
    public static function starred(array $tokens, ?Node $expression): bool
    {
        return $tokens !== [] && $tokens[count($tokens) - 1]->text === '*' && !self::composite($expression);
    }

    /**
     * Whether a select item is PostgreSQL `(value).*` or `$n.*`, which expands a composite value rather than a relation.
     */
    public static function composite(?Node $expression): bool
    {
        $value = $expression === null ? null : Tree::child($expression, ['c_expr']);
        return $value !== null && Tree::child($value, ['opt_indirection']) !== null;
    }
}
