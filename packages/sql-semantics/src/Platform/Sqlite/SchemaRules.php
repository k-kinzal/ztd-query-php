<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SchemaRules as Contract;
use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\ConstraintKind;
use SqlSemantics\Core\Schema\TableConstraint;
use SqlSemantics\Core\Schema\TableDefinition;

/**
 * Sqlite SchemaRules implementation.
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
        Tree::assertChildren($source, ['create_table', 'create_table_args'], []);
        Tree::assertChildren($header, ['createkw', 'nm', 'dbnm'], ['TABLE']);
        $arguments = Tree::child($source, ['create_table_args']);
        if ($arguments === null) {
            Tree::unsupported($source, 'table arguments');
        }
        Tree::assertChildren($arguments, ['columnlist', 'conslist_opt'], ['(', ')']);
        return;
    }

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array
    {
        $columns = [];
        foreach ($create->find('columnlist') as $list) {
            $column = Tree::child($list, ['columnname']);
            if ($column !== null) {
                $attributes = Tree::child($list, ['carglist']);
                $columns[] = [$column, $attributes === null ? [] : Tree::outer($attributes, ['ccons'])];
            }
        }
        return array_reverse($columns);
    }

    /**
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, array $primary, array $constraints): bool
    {
        if ($column->type->name !== 'integer' || count($primary) !== 1) {
            return false;
        }
        foreach ($constraints as $constraint) {
            if ($constraint->kind === ConstraintKind::PrimaryKey && str_contains(strtoupper(Tree::text($constraint->source)), 'DESC')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Selects the syntax node that owns the complete declaration.
     */
    public function schemaNode(Node $statement, Node $create): Node
    {
        return $statement;
    }

    /**
     * Returns the declaration identity used for duplicate detection.
     */
    public function tableKey(TableDefinition $table): string
    {
        return strtolower($table->schema . "\x00" . $table->name);
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    public function qualify(Node $header, array $parts, Identifiers $identifiers): array
    {
        $database = Tree::child($header, ['dbnm']);
        return $database === null ? $parts : [$parts[0], ...$identifiers->parts($database)];
    }
}
