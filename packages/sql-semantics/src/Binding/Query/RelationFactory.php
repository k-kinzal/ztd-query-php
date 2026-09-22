<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
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
        $document = Document\DocumentRelationBinder::bind($source, $function, $context, $parent, $scopeId);
        if ($document !== null) {
            return $document;
        }
        $scope = $parent ?? new Scope($context->tables->identifiers, queries: $context);
        $expression = (new ExpressionBinder())->bind($function, $scope);
        $aliasNode = QueryNodes::local($source, ['func_alias_clause', 'alias_clause', 'as', 'opt_table_alias'])[0] ?? null;
        $names = $aliasNode === null ? [] : $this->aliases($aliasNode, $context);
        $name = $names[0] ?? strtolower($expression->spelling() ?? 'function');
        $labels = array_slice($names, 1);
        if ($labels === []) {
            $labels = in_array($expression->spelling(), ['JSON_EACH', 'JSON_TREE'], true) ? ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'] : [$name];
        }
        $outputs = [];
        foreach ($labels as $index => $label) {
            $type = in_array($expression->spelling(), ['JSON_EACH', 'JSON_TREE'], true) ? TypeDescriptor::builtin($scope->identifiers->dialect, in_array($label, ['id', 'parent'], true) ? 'integer' : (in_array($label, ['type', 'fullkey', 'path'], true) ? 'text' : 'dynamic')) : $expression->type;
            $value = $expression->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $expression->nullability));
            $outputs[] = new OutputColumn($index, $label, $value);
        }
        $declaration = QueryRelation::columns($outputs, $name, [], $source);
        $table = new \SqlSemantics\Model\Relation\FunctionRelation($context->ids->relation(), $scopeId, $declaration, $name, $source, $expression, $outputs, $labels);
        return new BoundRelation($table, new Scope($scope->identifiers, [$table], parent: $parent, queries: $context));
    }

    /**
     * Hides the original join namespace behind the result alias.
     */
    public function alias(BoundRelation $relation, Node $alias, Node $source, QueryContext $context, string $scopeId): BoundRelation
    {
        $names = $this->aliases($alias, $context);
        $outputs = (new \SqlSemantics\Binding\ProjectionBinder())->star([], $relation->scope, $source, 0);
        $id = $context->ids->scope();
        $inputs = array_map(static fn (TableUse $input): TableUse => $input->withScope($id), $relation->scope->relations);
        $name = $names[0] ?? $id;
        $declaration = QueryRelation::columns($outputs, $name, array_slice($names, 1), $source);
        $table = new \SqlSemantics\Model\Relation\AliasedRelation($context->ids->relation(), $scopeId, $declaration, $name, $source, $this->rescope($relation->relation, array_column($inputs, null, 'id')), $outputs, array_slice($names, 1));
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
        return $relation->withInputs($this->rescope($relation->left, $inputs), $this->rescope($relation->right, $inputs));
    }

    /**
     * @return list<string>
     */
    public function aliases(Node $node, QueryContext $context): array
    {
        return array_values(array_filter($context->tables->identifiers->parts($node), static fn (string $name): bool => !in_array(strtoupper($name), ['AS', '(', ')', ','], true)));
    }
}
