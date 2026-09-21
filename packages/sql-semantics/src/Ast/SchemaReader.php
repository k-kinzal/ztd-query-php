<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use Closure;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
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
     * Binds the dependencies used for semantic binding.
     *
     * @param Closure(string, string, Node): void|null $onDiagnostic Optional analysis diagnostic receiver
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema, public readonly ?Closure $onDiagnostic = null)
    {
    }

    /**
     * Reports an invalid declaration without discarding other declarations during analysis.
     *
     * @throws SemanticException
     */
    public function report(string $reason, string $message, Node $source): void
    {
        if ($this->onDiagnostic === null) {
            throw new SemanticException($reason, $message, $source);
        }
        ($this->onDiagnostic)($reason, $message, $source);
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
                $create = Tree::outer($statement, ['CreateStmt', 'create_table_stmt', 'create_table'])[0] ?? null;
                if ($create === null) {
                    Tree::invalid($statement, 'schema statement');
                }
                $table = $this->table($this->identifiers->dialect === Dialect::Sqlite ? $statement : $create);
                $key = $table->schema . "\0" . $table->name;
                if ($this->identifiers->dialect === Dialect::Sqlite) {
                    $key = strtolower($key);
                }
                if (isset($tables[$key])) {
                    $this->report('duplicate-table', 'Duplicate table declaration: ' . $table->name, $create);
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
        $header = Tree::outer($create, ['create_table'])[0] ?? $create;
        $nameNode = Tree::outer($header, ['qualified_name', 'table_ident', 'nm'])[0] ?? null;
        if ($nameNode === null) {
            Tree::invalid($create, 'table name');
        }
        $parts = $this->identifiers->parts($nameNode);
        $sqliteDb = Tree::child($header, ['dbnm']);
        if ($sqliteDb !== null) {
            $parts = [$parts[0], ...$this->identifiers->parts($sqliteDb)];
        }
        $columns = [];
        $constraints = [];
        foreach ($this->columnNodes($create) as [$column, $attributes]) {
            [$definition, $localConstraints] = (new ColumnReader($this->identifiers))->read($column, $attributes);
            $columns[] = $definition;
            array_push($constraints, ...$localConstraints);
        }
        foreach ((new ConstraintGroups())->read(Tree::outer($create, ['TableConstraint', 'table_constraint_def', 'tcons'])) as $node) {
            $constraint = (new ConstraintReader($this->identifiers))->read($node);
            if ($constraint !== null) {
                $constraints[] = $constraint;
            }
        }
        $columns = $this->primaryKeys($columns, $constraints, $create);

        return new TableDefinition(count($parts) === 2 ? $parts[0] : $this->defaultSchema, $parts[count($parts) - 1], $columns, $constraints, $create);
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
            $columns[] = [$column, Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute'])];
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
            $this->report('duplicate-column', 'Duplicate column declaration.', $source);
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            foreach ($constraint->columns as $name) {
                if (!in_array($this->identifiers->dialect === Dialect::PostgreSql ? $name : strtolower($name), $names, true)) {
                    $this->report('unknown-column', 'Constraint references unknown column: ' . $name, $constraint->source);
                }
            }
            if ($constraint->kind === ConstraintKind::PrimaryKey) {
                if ($primary !== []) {
                    $this->report('duplicate-primary-key', 'More than one primary key.', $constraint->source);
                }
                $primary = array_map(fn (string $name): string => $this->identifiers->dialect === Dialect::PostgreSql ? $name : strtolower($name), $constraint->columns);
            }
        }
        $result = [];
        foreach ($columns as $column) {
            $notNull = in_array($this->identifiers->dialect === Dialect::PostgreSql ? $column->name : strtolower($column->name), $primary, true) && $this->primaryNotNull($column, $primary, $constraints) || (in_array(strtolower($column->name), $primary, true) && $this->identifiers->dialect === Dialect::Sqlite && (str_contains(strtoupper(Tree::text($source)), 'WITHOUT ROWID') || str_contains(strtoupper(Tree::text($source)), 'STRICT')));
            $result[] = new ColumnDefinition($column->name, $column->type, $notNull ? Nullability::NotNull : $column->nullability, $column->source, $column->defaultExpression, $column->attributes, $column->generatedExpression);
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
