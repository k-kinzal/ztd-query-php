<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SchemaRules as Contract;
use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\TableConstraint;
use SqlSemantics\Core\Schema\TableDefinition;

/**
 * PostgreSql SchemaRules implementation.
 *
 * @visibility SqlSemantics
 */
final class SchemaRules implements Contract
{
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
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, array $primary, array $constraints): bool
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
