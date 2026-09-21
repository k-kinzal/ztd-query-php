<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Exposes aliased joins and set-returning functions as relation occurrences.
 *
 * @visibility SqlSemantics
 */
final class RelationFactory
{
    /**
     * Gives a table function its own relation identity and bound argument graph.
     */
    public function function(Node $source, Node $function, QueryContext $context, ?Scope $parent, string $scopeId): BoundRelation
    {
        $scope = $parent ?? new Scope($context->tables->identifiers, queries: $context);
        $expression = (new ExpressionBinder())->bind($function, $scope);
        $aliasNode = QueryNodes::local($source, ['func_alias_clause', 'alias_clause', 'as', 'opt_table_alias'])[0] ?? null;
        $names = $aliasNode === null ? [] : $this->aliases($aliasNode, $context);
        $name = $names[0] ?? strtolower($expression->symbol ?? 'function');
        $labels = array_slice($names, 1);
        if ($labels === []) {
            $labels = in_array($expression->symbol, ['JSON_EACH', 'JSON_TREE'], true) ? ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'] : [$name];
        }
        $outputs = [];
        foreach ($labels as $index => $label) {
            $type = in_array($expression->symbol, ['JSON_EACH', 'JSON_TREE'], true) ? new TypeDescriptor($scope->identifiers->dialect, in_array($label, ['id', 'parent'], true) ? 'integer' : (in_array($label, ['type', 'fullkey', 'path'], true) ? 'text' : 'dynamic')) : $expression->type;
            $value = new Expression($expression->kind, $type, $expression->nullability, $expression->source, $expression->operands, symbol: $expression->symbol);
            $outputs[] = new OutputColumn($index, $label, $value);
        }
        $declared = $this->tableColumns($function, $expression, $scope);
        if ($declared !== []) {
            $outputs = $declared;
        }
        $query = new BoundSelect($context->ids->scope(), null, [], $outputs, null, false, [], null, null, $function);
        $declaration = QueryRelation::declaration($query, $name, [], $source);
        $table = new TableUse($context->ids->relation(), $scopeId, $declaration, $name, $source, $query);
        return new BoundRelation($table, new Scope($scope->identifiers, [$table], parent: $parent, queries: $context));
    }

    /**
     * Reads the explicit output schema of JSON_TABLE and XMLTABLE, including nested columns.
     *
     * @return list<OutputColumn>
     */
    public function tableColumns(Node $node, Expression $call, Scope $scope, bool $nested = false): array
    {
        $outputs = [];
        foreach (\SqlSemantics\Ast\Tree::outer($node, ['json_table_column_definition', 'jt_column', 'xmltable_column_el']) as $column) {
            $name = \SqlSemantics\Ast\Tree::child($column, ['ColId', 'ident']);
            if ($name === null) {
                $children = \SqlSemantics\Ast\Tree::child($column, ['columns_clause', 'json_table_column_definition_list']);
                if ($children !== null) {
                    foreach ($this->tableColumns($children, $call, $scope, true) as $output) {
                        $outputs[] = new OutputColumn(count($outputs), $output->name, $output->expression);
                    }
                }
                continue;
            }
            $typeNode = \SqlSemantics\Ast\Tree::child($column, ['Typename', 'type']);
            $type = $typeNode === null ? new TypeDescriptor($scope->identifiers->dialect, $scope->identifiers->dialect === \SqlSemantics\Dialect::MySql ? 'int unsigned' : 'integer') : (new \SqlSemantics\Ast\TypeReader($scope->identifiers->dialect))->read($typeNode);
            $nullable = $typeNode === null && !$nested ? \SqlSemantics\Type\Nullability::NotNull : \SqlSemantics\Type\Nullability::MaybeNull;
            $value = new Expression(\SqlSemantics\Model\ExpressionKind::Function, $type, $nullable, $column, [$call], symbol: $typeNode === null ? 'ORDINALITY' : $call->symbol);
            $outputs[] = new OutputColumn(count($outputs), $scope->identifiers->parts($name)[0], $value);
        }
        return $outputs;
    }

    /**
     * Hides the original join namespace behind the result alias.
     */
    public function alias(BoundRelation $relation, Node $alias, Node $source, QueryContext $context, string $scopeId): BoundRelation
    {
        $names = $this->aliases($alias, $context);
        $outputs = (new \SqlSemantics\Binding\ProjectionBinder())->star([], $relation->scope, $source, 0);
        $id = $context->ids->scope();
        $inputs = array_map(static fn (TableUse $input): TableUse => new TableUse($input->id, $id, $input->declaration, $input->alias, $input->source, $input->query), $relation->scope->relations);
        $query = new BoundSelect($id, $this->rescope($relation->relation, array_column($inputs, null, 'id')), $inputs, $outputs, null, false, [], null, null, $source);
        $name = $names[0] ?? $query->scopeId;
        $declaration = QueryRelation::declaration($query, $name, array_slice($names, 1), $source);
        $table = new TableUse($context->ids->relation(), $scopeId, $declaration, $name, $source, $query);
        return new BoundRelation($table, new Scope($context->tables->identifiers, [$table], parent: $relation->scope->parent, queries: $context));
    }

    /**
     * Reuses relation identities while moving an aliased join's inputs into its inner scope.
     *
     * @param array<string, TableUse> $inputs Relation occurrences owned by the inner scope
     */
    public function rescope(\SqlSemantics\Model\Join|TableUse $relation, array $inputs): \SqlSemantics\Model\Join|TableUse
    {
        if ($relation instanceof TableUse) {
            return $inputs[$relation->id];
        }
        return new \SqlSemantics\Model\Join($relation->id, $relation->kind, $this->rescope($relation->left, $inputs), $this->rescope($relation->right, $inputs), $relation->condition, $relation->source);
    }

    /**
     * @return list<string>
     */
    public function aliases(Node $node, QueryContext $context): array
    {
        return array_values(array_filter($context->tables->identifiers->parts($node), static fn (string $name): bool => !in_array(strtoupper($name), ['AS', '(', ')', ','], true)));
    }
}
