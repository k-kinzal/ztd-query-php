<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\ConstraintKind;
use SqlSemantics\Core\Schema\TableConstraint;
use SqlSemantics\Core\Schema\TableDefinition;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Nullability;

/**
 * Builds a schema snapshot from CREATE TABLE syntax nodes.
 *
 * @visibility SqlSemantics
 */
final class SchemaReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema)
    {
    }

    /**
     * @param list<Node> $trees
     * @return list<TableDefinition>
     * @throws SemanticException
     */
    public function read(array $trees): array
    {
        $tables = [];
        foreach ($trees as $tree) {
            foreach (StatementList::read($tree, $this->identifiers->dialect) as $statement) {
                $create = Tree::outer($statement, $this->identifiers->dialect->platform()->syntax()->nodes('createTable'))[0] ?? null;
                if ($create === null) {
                    Tree::unsupported($statement, 'schema statement');
                }
                $table = $this->table($this->identifiers->dialect->platform()->schema()->schemaNode($statement, $create));
                $key = $this->identifiers->dialect->platform()->schema()->tableKey($table);
                if (isset($tables[$key])) {
                    throw new SemanticException('duplicate-table', 'Duplicate table declaration: ' . $table->name, $create);
                }
                $tables[$key] = $table;
            }
        }

        return array_values($tables);
    }

    /**
     * Reads one table and promotes the nullability of primary key columns.
     */
    public function table(Node $create): TableDefinition
    {
        $header = Tree::outer($create, $this->identifiers->dialect->platform()->syntax()->nodes('createHeader'))[0] ?? $create;
        $nameNode = Tree::child($header, $this->identifiers->dialect->platform()->syntax()->nodes('tableName'));
        if ($nameNode === null) {
            Tree::unsupported($create, 'table name');
        }
        $parts = $this->identifiers->parts($nameNode);
        $parts = $this->identifiers->dialect->platform()->schema()->qualify($header, $parts, $this->identifiers);
        if (count($parts) > 2) {
            Tree::unsupported($nameNode, 'three-part table name');
        }
        $this->validate($create, $header);
        $columns = [];
        $constraints = [];
        foreach ($this->columnNodes($create) as [$column, $attributes]) {
            [$definition, $localConstraints] = (new ColumnReader($this->identifiers))->read($column, $attributes);
            $columns[] = $definition;
            array_push($constraints, ...$localConstraints);
        }
        foreach (Tree::outer($create, $this->identifiers->dialect->platform()->syntax()->nodes('tableConstraint')) as $node) {
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
        $this->identifiers->dialect->platform()->schema()->validate($source, $header);
    }

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array
    {
        return $this->identifiers->dialect->platform()->schema()->columnNodes($create);
    }

    /**
     * @param list<ColumnDefinition> $columns
     * @param list<TableConstraint> $constraints
     * @return list<ColumnDefinition>
     * @throws SemanticException
     */
    public function primaryKeys(array $columns, array $constraints, Node $source): array
    {
        $names = array_map(fn (ColumnDefinition $column): string => $this->identifiers->dialect->platform()->names()->key($column->name), $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new SemanticException('duplicate-column', 'Duplicate column declaration.', $source);
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            foreach ($constraint->columns as $name) {
                if (!in_array($this->identifiers->dialect->platform()->names()->key($name), $names, true)) {
                    throw new SemanticException('unknown-column', 'Constraint references unknown column: ' . $name, $constraint->source);
                }
            }
            if ($constraint->kind === ConstraintKind::PrimaryKey) {
                if ($primary !== []) {
                    throw new SemanticException('duplicate-primary-key', 'More than one primary key.', $constraint->source);
                }
                $primary = array_map(fn (string $name): string => $this->identifiers->dialect->platform()->names()->key($name), $constraint->columns);
            }
        }
        $result = [];
        foreach ($columns as $column) {
            $notNull = in_array($this->identifiers->dialect->platform()->names()->key($column->name), $primary, true) && $this->primaryNotNull($column, $primary, $constraints);
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
        return $this->identifiers->dialect->platform()->schema()->primaryNotNull($column, $primary, $constraints);
    }
}
