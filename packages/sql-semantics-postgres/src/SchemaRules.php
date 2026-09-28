<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\ColumnAttributes;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SchemaRules as Contract;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Element;

/**
 * PostgreSql SchemaRules implementation.
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
     * Contradictory NULL constraints are invalid, in either order.
     * @throws SemanticException When NULL constraints conflict
     * @param list<Node> $attributes
     */
    public function nullability(Node $column, array $attributes, Nullability $implicit): Nullability
    {
        $facts = ColumnAttributes::nulls($attributes);
        if (in_array(Nullability::MaybeNull, $facts, true) && (in_array(Nullability::NotNull, $facts, true) || $implicit === Nullability::NotNull)) {
            throw new SemanticException('conflicting-nullability', 'Conflicting NULL and NOT NULL constraints.', $column);
        }
        return in_array(Nullability::NotNull, $facts, true) ? Nullability::NotNull : $implicit;
    }

    /**
     * Reads the expression inside an optionally named default constraint.
     */
    public function defaultValue(Node $attribute, ValueReader $values): Element
    {
        $expression = Tree::outer($attribute, ['b_expr'])[0] ?? null;
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
        foreach (Tree::outer($source, ['OptInherit', 'TableLikeClause', 'PartitionBoundSpec', 'TypedTableElementList']) as $inherited) {
            if ($inherited->tokens() !== []) {
                Tree::unsupported($inherited, 'catalog columns requiring another relation');
            }
        }
    }

    /**
     * @return list<Node>
     */
    public function options(Node $source): array
    {
        return array_values(array_filter(Tree::outer($source, ['OptWith', 'OptTableSpace', 'OnCommitOption', 'OptAccessMethod', 'PartitionSpec']), static fn (Node $node): bool => $node->tokens() !== []));
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
            $columns[] = [$column, Tree::outer($column, ['ColConstraint', 'column_attribute'])];
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
