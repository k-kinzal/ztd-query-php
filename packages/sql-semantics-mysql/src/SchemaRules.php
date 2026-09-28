<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\ColumnAttributes;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SchemaRules as Contract;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Element;

/**
 * MySql SchemaRules implementation.
 *
 * @visibility SqlSemantics
 */
final class SchemaRules implements Contract
{
    /**
     * Implicit namespaces searched before the session path for declared tables.
     * @return list<string>
     */
    public function implicitSchemas(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function keyColumns(Node $constraint, Identifiers $identifiers): array
    {
        return \SqlSemantics\Core\Ast\TokenGroups::keyNames(\SqlSemantics\Core\Ast\TokenGroups::parentheses($constraint->tokens())[0] ?? [], $identifiers);
    }

    /**
     * The last explicit NULL attribute wins.
     * @param list<Node> $attributes
     */
    public function nullability(Node $column, array $attributes, Nullability $implicit): Nullability
    {
        $facts = ColumnAttributes::nulls($attributes);
        return $facts === [] ? $implicit : $facts[count($facts) - 1];
    }

    /**
     * Removes the DEFAULT envelope while keeping its expression.
     */
    public function defaultValue(Node $attribute, ValueReader $values): Element
    {
        $expression = Tree::outer($attribute, ['now_or_signed_literal', 'expr'])[0] ?? null;
        if ($expression === null) {
            Tree::unsupported($attribute, 'default expression');
        }
        return $values->read($expression);
    }

    /**
     * Rejects declarations whose column state requires evaluating another relation.
     */
    public function validate(Node $source, Node $header): void
    {
        (new Declaration\AutoIncrement())->validate($source);
        foreach (Tree::outer($source, ['opt_create_table_options_etc', 'create3']) as $options) {
            foreach (Tree::outer($options, ['query_expression', 'query_expression_with_opt_locking_clauses', 'create_table_query_expression', 'create_select']) as $query) {
                Tree::unsupported($query, 'catalog columns derived from a query');
            }
        }
    }

    /**
     * @return list<Node>
     */
    public function options(Node $source): array
    {
        return array_values(array_filter(Tree::outer($source, ['create_table_option', 'opt_partitioning', 'table_constraint_def']), static fn (Node $node): bool => $node->tokens() !== []));
    }

    /**
     * Reports table-level primary key nullability.
     */
    public function primaryOptionsNotNull(Node $source): bool
    {
        return false;
    }

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array
    {
        $columns = [];
        foreach (Tree::outer($create, ['columnDef', 'column_def']) as $column) {
            $columns[] = [$column, Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute'])];
        }
        return $columns;
    }

    /**
     * Primary key columns are always nonnullable, whatever their declaration.
     *
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, Node $declaration, array $primary, array $constraints): bool
    {
        return true;
    }

    /**
     * Selects the syntax node that owns the complete declaration.
     */
    public function schemaNode(Node $statement, Node $create): Node
    {
        return $create;
    }

    /**
     * Returns the declaration identity used for duplicate detection.
     */
    public function tableKey(TableDefinition $table): string
    {
        return $table->schema . "\x00" . $table->name;
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    public function qualify(Node $header, array $parts, Identifiers $identifiers): array
    {
        return $parts;
    }
}
