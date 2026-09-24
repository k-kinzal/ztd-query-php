<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ColumnReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Schema\ColumnDefinition;
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
     * @return list<TableDefinition>
     * @throws SemanticException
     */
    public function apply(Node $statement): array
    {
        $nameNode = Tree::outer($statement, ['relation_expr', 'table_ident', 'fullname'])[0] ?? null;
        if ($nameNode === null) {
            throw new SemanticException('invalid-declaration', 'ALTER TABLE requires a table name.', $statement);
        }
        $table = $this->tables->resolve($this->tables->identifiers->parts($nameNode), $nameNode);
        $columns = $table->columns;
        $constraints = $table->constraints;
        foreach (Alter\AddedColumns::read($statement) as $column) {
            $attributes = Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute', 'gcol_attribute']);
            if ($column->name === 'columnname') {
                $attributes = Tree::outer($statement, ['ccons']);
            }
            [$definition, $local] = (new ColumnReader($this->tables->identifiers))->read($column, $attributes);
            $context = new \SqlSemantics\Binding\Query\QueryContext($this->tables);
            $scope = new \SqlSemantics\Binding\Scope($this->tables->identifiers, [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $table, new \SqlSemantics\Model\Relation\QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]), null, $statement)], queries: $context);
            $columns[] = ColumnBinder::bind($definition, $scope);
            array_push($constraints, ...array_map(static fn ($constraint): \SqlSemantics\Schema\TableConstraint => ConstraintBinder::bind($constraint, $scope), $local));
        }
        $name = $table->name;
        foreach (Tree::outer($statement, ['alter_table_cmd', 'alter_list_item', 'RenameStmt', 'cmd']) as $action) {
            [$columns, $name] = $this->action($action, $columns, $name);
        }
        $replacement = new TableDefinition($table->schema, $name, $columns, $constraints, $statement, indexes: $table->indexes, properties: $table->properties);
        return array_map(static fn (TableDefinition $candidate): TableDefinition => $candidate === $table ? $replacement : $candidate, $this->tables->schema->tables);
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
        if (!in_array($operation, ['ALTER', 'MODIFY', 'CHANGE'], true)) {
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
                $scope = new \SqlSemantics\Binding\Scope($this->tables->identifiers, queries: new \SqlSemantics\Binding\Query\QueryContext($this->tables));
                $generation = new \SqlSemantics\Schema\Column\SuppliedColumn((new DefinitionBinder())->expression($default, $scope));
            }
            $result[] = new ColumnDefinition($column->name, $type, $nullability, $action, $generation, $column->attributes);
        }
        return $result;
    }

}
