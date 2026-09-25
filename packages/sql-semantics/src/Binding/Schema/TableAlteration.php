<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnDeclarations;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * Applies table and column changes to immutable schema snapshots.
 *
 * @visibility SqlSemantics
 */
final class TableAlteration
{
    /**
     * Uses the current snapshot for resolving the table being altered.
     */
    public function __construct(public readonly TableResolver $tables)
    {
    }

    /**
     * Applies the statement's column declarations and actions in SQL order; the constraints written on an added or
     * redeclared column join the table, table-level keys, constraints, and indexes are added after the ones the
     * statement drops, and every primary key column outside SQLite becomes NOT NULL.
     * @return list<TableDefinition>
     * @throws SemanticException
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function apply(Node $statement): array
    {
        $nameNode = Tree::outer($statement, ['relation_expr', 'table_ident', 'fullname'])[0] ?? null;
        if ($nameNode === null) {
            throw new SemanticException('invalid-declaration', 'ALTER TABLE requires a table name.', $statement);
        }
        $table = $this->tables->resolve($this->tables->identifiers->parts($nameNode), $nameNode);
        $mysql = $this->tables->identifiers->dialect === Dialect::MySql;
        $items = $mysql ? Tree::outer($statement, ['alter_list_item']) : [];
        $added = $mysql ? [] : array_map(fn (Node $column): array => (new ColumnReader($this->tables->identifiers))->read($column, $this->attributes($statement, $column)), Alter\AddedColumns::read($statement));
        $scope = $this->scope($table, $statement, ColumnDeclarations::declared($items, new Scope($this->tables->identifiers, queries: new QueryContext($this->tables)), [...$table->columns, ...array_map(static fn (array $declaration): ColumnDefinition => new ColumnDefinition($declaration[0]->name, $declaration[0]->type, $declaration[0]->nullability, $declaration[0]->source), $added)]));
        ColumnDeclarations::nullKeys($items, $scope);
        $columns = $table->columns;
        $constraints = [];
        foreach ($added as [$definition, $local]) {
            $columns[] = ColumnBinder::bind($definition, $scope);
            array_push($constraints, ...array_map(static fn ($constraint): TableConstraint => ConstraintBinder::bind($constraint, $scope), $local));
        }
        if ($this->tables->identifiers->dialect === Dialect::Sqlite && array_filter($constraints, static fn (TableConstraint $constraint): bool => $constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey || $constraint instanceof \SqlSemantics\Schema\Constraint\UniqueKey) !== []) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AddedColumnKey, $statement);
        }
        $name = $table->name;
        $keys = new Alter\KeyChanges($table, [...$table->constraints, ...$constraints], $table->indexes);
        $context = new QueryContext($this->tables);
        foreach (Tree::outer($statement, ['alter_table_cmd', 'alter_list_item', 'RenameStmt', 'cmd']) as $action) {
            $redeclared = $mysql ? Alter\ColumnRedeclarations::apply($action, $columns, $scope) : null;
            if ($redeclared !== null) {
                [$columns, $local] = $redeclared;
                $keys = new Alter\KeyChanges($table, [...$keys->constraints, ...$local], $keys->indexes, $keys->additions);
                continue;
            }
            $changed = $keys->action($action, $scope, $context);
            if ($changed !== null) {
                $keys = $changed;
                continue;
            }
            [$columns, $name] = $this->action($action, $columns, $name);
        }
        [$constraints, $indexes] = $keys->add($scope, $context);
        $replacement = new TableDefinition($table->schema, $name, $this->primaryKeys($columns, $constraints), $constraints, $statement, indexes: $indexes, properties: $table->properties);
        return array_map(static fn (TableDefinition $candidate): TableDefinition => $candidate === $table ? $replacement : $candidate, $this->tables->schema->tables);
    }

    /**
     * Returns the attribute nodes of a PostgreSQL or SQLite added column; SQLite writes them after its columnname node.
     * @return list<Node>
     */
    public function attributes(Node $statement, Node $column): array
    {
        return $column->name === 'columnname' ? Tree::outer($statement, ['ccons']) : Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute', 'gcol_attribute']);
    }

    /**
     * Builds the column namespace of the altered table, holding the given columns under the table's qualified name.
     * @param list<ColumnDefinition> $columns
     */
    public function scope(TableDefinition $table, Node $statement, array $columns): Scope
    {
        $declaration = new TableDefinition($table->schema, $table->name, $columns, $table->constraints, $table->source, $table->resolved, $table->indexes, $table->properties);
        $name = new \SqlSemantics\Model\Relation\QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]);
        return new Scope($this->tables->identifiers, [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $declaration, $name, null, $statement)], queries: new QueryContext($this->tables));
    }

    /**
     * Declares every primary key column NOT NULL, as MySQL and PostgreSQL do; a SQLite primary key keeps its columns' nullability.
     * @param list<ColumnDefinition> $columns
     * @param list<TableConstraint> $constraints
     * @return list<ColumnDefinition>
     */
    public function primaryKeys(array $columns, array $constraints): array
    {
        $identifiers = $this->tables->identifiers;
        if ($identifiers->dialect === Dialect::Sqlite) {
            return $columns;
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey) {
                array_push($primary, ...$constraint->localColumns());
            }
        }
        return array_map(static fn (ColumnDefinition $column): ColumnDefinition => $column->nullability !== Nullability::NotNull && array_filter($primary, static fn (string $name): bool => $identifiers->equal($name, $column->name)) !== [] ? new ColumnDefinition($column->name, $column->type, Nullability::NotNull, $column->source, $column->generation, $column->attributes) : $column, $columns);
    }

    /**
     * @param list<ColumnDefinition> $columns
     * @return array{list<ColumnDefinition>, string}
     */
    public function action(Node $action, array $columns, string $tableName): array
    {
        $tokens = $action->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        $identifiers = $this->tables->identifiers;
        $rename = array_search('RENAME', $words, true);
        $to = array_search('TO', $words, true);
        if ($rename !== false && $to !== false && isset($tokens[$to + 1])) {
            $newName = $identifiers->name($tokens[$to + 1]);
            if ($to === $rename + 1) {
                return [$columns, $newName];
            }
            $oldName = $identifiers->name($tokens[$to - 1]);
            $columns = array_map(static fn (ColumnDefinition $column): ColumnDefinition => $identifiers->equal($column->name, $oldName) ? $column->withName($newName) : $column, $columns);
        }
        $drop = array_search('DROP', $words, true);
        if ($drop !== false && ($words[$drop + 1] ?? '') !== 'CONSTRAINT') {
            $index = $drop + (($words[$drop + 1] ?? '') === 'COLUMN' ? 2 : 1);
            if (isset($tokens[$index]) && !in_array($words[$index], ['DEFAULT', 'NOT'], true)) {
                $name = $identifiers->name($tokens[$index]);
                $columns = array_values(array_filter($columns, static fn (ColumnDefinition $column): bool => !$identifiers->equal($column->name, $name)));
            }
        }
        return [$this->columnAttributes($action, $columns), $tableName];
    }
    /**
     * @param list<ColumnDefinition> $columns
     * @return list<ColumnDefinition>
     */
    public function columnAttributes(Node $action, array $columns): array
    {
        $operation = strtoupper($action->tokens()[0]->text ?? '');
        if ($operation !== 'ALTER' && ($operation === '' || $this->tables->identifiers->dialect === Dialect::MySql || !in_array($operation, ['MODIFY', 'CHANGE'], true))) {
            return $columns;
        }
        $nameNode = Tree::child($action, ['ColId', 'ident']);
        if ($nameNode === null) {
            return $columns;
        }
        $name = $this->tables->identifiers->parts($nameNode)[0];
        $typeNode = Tree::outer($action, ['Typename', 'type'])[0] ?? null;
        $text = implode(' ', Tree::keywords($action));
        $result = [];
        foreach ($columns as $column) {
            if (!$this->tables->identifiers->equal($name, $column->name)) {
                $result[] = $column;
                continue;
            }
            $type = $typeNode === null ? $column->type : (new \SqlSemantics\Ast\TypeReader($this->tables->identifiers->dialect))->read($typeNode);
            $nullability = $column->nullability;
            if (str_contains($text, 'DROP NOT NULL')) {
                $nullability = Nullability::MaybeNull;
            } elseif (str_contains($text, 'NOT NULL')) {
                $nullability = Nullability::NotNull;
            }
            $generation = $column->generation;
            if (str_contains($text, 'DROP DEFAULT')) {
                $generation = new \SqlSemantics\Schema\Column\SuppliedColumn();
            }
            if (str_contains($text, 'SET DEFAULT')) {
                $default = Tree::outer($action, ['a_expr', 'expr'])[0] ?? $action;
                $scope = new Scope($this->tables->identifiers, queries: new QueryContext($this->tables));
                $generation = new \SqlSemantics\Schema\Column\SuppliedColumn((new DefinitionBinder())->expression($default, $scope));
            }
            $result[] = new ColumnDefinition($column->name, $type, $nullability, $action, $generation, $column->attributes, nullDeclared: $column->nullDeclared && $nullability === $column->nullability);
        }
        return $result;
    }

}
