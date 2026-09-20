<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Catalog;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * Builds a schema snapshot from CREATE TABLE syntax nodes.
 *
 * @visibility SqlSemantics
 */
final class SchemaReader
{
    /**
     * Binds the dependencies used for this analysis.
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema)
    {
    }

    /**
     * @param list<Node> $trees
     * @throws SemanticException
     */
    public function read(array $trees): Catalog
    {
        $tables = [];
        foreach ($trees as $tree) {
            foreach (StatementList::read($tree, $this->identifiers->dialect) as $statement) {
                $create = Tree::outer($statement, ['CreateStmt', 'create_table_stmt', 'create_table'])[0] ?? null;
                if ($create === null) {
                    Tree::unsupported($statement, 'schema statement');
                }
                $table = $this->table($this->identifiers->dialect === Dialect::Sqlite ? $statement : $create);
                $key = $table->schema . "\0" . $table->name;
                if ($this->identifiers->dialect === Dialect::Sqlite) {
                    $key = strtolower($key);
                }
                if (isset($tables[$key])) {
                    throw new SemanticException('duplicate-table', 'Duplicate table declaration: ' . $table->name, $create);
                }
                $tables[$key] = $table;
            }
        }

        return new Catalog($this->identifiers->dialect, array_values($tables));
    }

    /**
     * Reads one table and promotes the nullability of primary key columns.
     */
    public function table(Node $create): TableDefinition
    {
        $header = Tree::outer($create, ['create_table'])[0] ?? $create;
        $nameNode = Tree::child($header, ['qualified_name', 'table_ident', 'nm']);
        if ($nameNode === null) {
            Tree::unsupported($create, 'table name');
        }
        $parts = $this->identifiers->parts($nameNode);
        $sqliteDb = Tree::child($header, ['dbnm']);
        if ($sqliteDb !== null) {
            $parts = [$parts[0], ...$this->identifiers->parts($sqliteDb)];
        }
        if (count($parts) > 2) {
            Tree::unsupported($nameNode, 'catalog-qualified table');
        }
        $this->validate($create, $header);
        $columns = [];
        $constraints = [];
        foreach ($this->columnNodes($create) as [$column, $attributes]) {
            [$definition, $localConstraints] = (new ColumnReader($this->identifiers))->read($column, $attributes);
            $columns[] = $definition;
            array_push($constraints, ...$localConstraints);
        }
        foreach (Tree::outer($create, ['TableConstraint', 'table_constraint_def', 'tcons']) as $node) {
            $constraint = (new ConstraintReader($this->identifiers))->read($node);
            if ($constraint === null) {
                Tree::unsupported($node, 'table constraint');
            }
            $constraints[] = $constraint;
        }
        if ($columns === []) {
            Tree::unsupported($create, 'CREATE TABLE without column declarations');
        }
        $columns = $this->primaryKeys($columns, $constraints, $create);

        return new TableDefinition(count($parts) === 2 ? $parts[0] : $this->defaultSchema, $parts[count($parts) - 1], $columns, $constraints, $create);
    }

    /**
     * Rejects table options that change the declared schema semantics.
     */
    public function validate(Node $source, Node $header): void
    {
        if ($this->identifiers->dialect === Dialect::Sqlite) {
            Tree::assertChildren($source, ['create_table', 'create_table_args'], []);
            Tree::assertChildren($header, ['createkw', 'nm', 'dbnm'], ['TABLE']);
            $arguments = Tree::child($source, ['create_table_args']);
            if ($arguments === null) {
                Tree::unsupported($source, 'table arguments');
            }
            Tree::assertChildren($arguments, ['columnlist', 'conslist_opt'], ['(', ')']);
            return;
        }
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
        if ($this->identifiers->dialect === Dialect::Sqlite) {
            foreach ($create->find('columnlist') as $list) {
                $column = Tree::child($list, ['columnname']);
                if ($column !== null) {
                    $attributes = Tree::child($list, ['carglist']);
                    $columns[] = [$column, $attributes === null ? [] : Tree::outer($attributes, ['ccons'])];
                }
            }
            return array_reverse($columns);
        }
        foreach (Tree::outer($create, ['columnDef', 'column_def']) as $column) {
            $columns[] = [$column, Tree::outer($column, ['ColConstraint', 'column_attribute'])];
        }

        return $columns;
    }

    /**
     * @param list<ColumnDefinition> $columns
     * @param list<TableConstraint> $constraints
     * @return list<ColumnDefinition>
     * @throws SemanticException
     */
    public function primaryKeys(array $columns, array $constraints, Node $source): array
    {
        $names = array_map(fn (ColumnDefinition $column): string => $this->identifiers->dialect === Dialect::PostgreSql ? $column->name : strtolower($column->name), $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new SemanticException('duplicate-column', 'Duplicate column declaration.', $source);
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            foreach ($constraint->columns as $name) {
                if (!in_array($this->identifiers->dialect === Dialect::PostgreSql ? $name : strtolower($name), $names, true)) {
                    throw new SemanticException('unknown-column', 'Constraint references unknown column: ' . $name, $constraint->source);
                }
            }
            if ($constraint->kind === ConstraintKind::PrimaryKey) {
                if ($primary !== []) {
                    throw new SemanticException('duplicate-primary-key', 'More than one primary key.', $constraint->source);
                }
                $primary = array_map(fn (string $name): string => $this->identifiers->dialect === Dialect::PostgreSql ? $name : strtolower($name), $constraint->columns);
            }
        }
        $result = [];
        foreach ($columns as $column) {
            $notNull = in_array($this->identifiers->dialect === Dialect::PostgreSql ? $column->name : strtolower($column->name), $primary, true) && $this->primaryNotNull($column, $primary, $constraints);
            $result[] = new ColumnDefinition($column->name, $column->type, $notNull ? Nullability::NotNull : $column->nullability, $column->source, $column->defaultExpression);
        }

        return $result;
    }

    /**
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, array $primary, array $constraints): bool
    {
        if ($this->identifiers->dialect !== Dialect::Sqlite) {
            return true;
        }
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
}
