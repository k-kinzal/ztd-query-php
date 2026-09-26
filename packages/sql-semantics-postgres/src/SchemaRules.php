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
     * Rejects table options that change the declared schema semantics.
     */
    public function validate(Node $source, Node $header): void
    {
        Tree::assertChildren($source, ['qualified_name', 'OptTableElementList', 'table_ident', 'table_element_list'], ['CREATE', 'TABLE', '(', ')']);
        foreach ($source->find('field_def') as $field) {
            Tree::assertChildren($field, ['type', 'opt_column_attribute_list'], []);
        }
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
