<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use Closure;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\ColumnDefinition;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Ast\Declaration\TableDefinition;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\ConstraintKind;
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
     * @param string|null $grammarVersion Grammar release tag; MySQL enforces an inline column REFERENCES from 9.0 on and ignores it before
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema, public readonly ?Closure $onDiagnostic = null, public readonly ?string $grammarVersion = null)
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
                $create = Tree::outer($statement, ['CreateStmt', 'create_table_stmt', 'create_table', 'create'])[0] ?? null;
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
     * @throws \SqlSemantics\InvalidSql
     */
    public function table(Node $create): TableDefinition
    {
        $header = Tree::outer($create, ['create_table'])[0] ?? $create;
        $nameNode = Tree::outer($header, ['qualified_name', 'table_ident', 'nm'])[0] ?? null;
        if ($nameNode === null) {
            Tree::invalid($create, 'table name');
        }
        $parts = $this->identifiers->dialect === Dialect::PostgreSql ? \SqlSemantics\Binding\Statement\Utility\QualifiedNames::read($nameNode, $this->identifiers)->parts : $this->identifiers->parts($nameNode);
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
        foreach ((new ConstraintGroups())->read(Tree::outer($create, ['TableConstraint', 'table_constraint_def', 'key_def', 'tcons'])) as $node) {
            $constraint = (new ConstraintReader($this->identifiers))->read($node);
            if ($constraint !== null) {
                $constraints[] = $constraint;
            }
        }
        Definition\PrimaryKeyNulls::reject($columns, $constraints, $this->identifiers->dialect, $this->grammarVersion);
        $columns = $this->autoIncrement($this->primaryKeys($columns, $constraints, $create), $constraints);

        $namespace = $this->namespace($header, $parts);
        $name = $parts[count($parts) - 1];
        $indexes = (new Definition\IndexReader($this->identifiers, $this->defaultSchema))->table($create, [$namespace, $name]);
        $options = Definition\OptionReader::read($create, $this->identifiers, ['columnDef', 'column_def', 'columnlist', 'TableConstraint', 'table_constraint_def', 'key_def', 'tcons']);
        return new TableDefinition($namespace, $name, $columns, $constraints, $create, indexes: $indexes, options: $options, catalog: count($parts) === 3 ? $parts[0] : null, ifNotExists: $this->ifNotExists($create, $nameNode));
    }

    /**
     * Reports whether IF NOT EXISTS is written among the words before the table name, never inside the declared elements.
     */
    public function ifNotExists(Node $create, Node $name): bool
    {
        $start = $name->tokens()[0]->offset ?? 0;
        $words = array_map(static fn (\SqlParser\Lexer\Token $token): string => strtoupper($token->text), array_values(array_filter($create->tokens(), static fn (\SqlParser\Lexer\Token $token): bool => $token->offset < $start)));
        return str_contains(' ' . implode(' ', $words) . ' ', ' IF NOT EXISTS ');
    }

    /**
     * Returns the schema of a declared table: a temporary table lives in PostgreSQL's pg_temp or SQLite's temp schema, and naming another schema is rejected as the server does.
     *
     * @param list<string> $parts Written name parts
     * @throws \SqlSemantics\InvalidSql
     */
    public function namespace(Node $header, array $parts): string
    {
        $written = count($parts) >= 2 ? $parts[count($parts) - 2] : null;
        $dialect = $this->identifiers->dialect;
        $marker = Tree::child($header, $dialect === Dialect::PostgreSql ? ['OptTemp'] : ['temp']);
        if ($dialect === Dialect::MySql || $marker === null || !str_contains(strtoupper(Tree::text($marker)), 'TEMP')) {
            return $written ?? $this->defaultSchema;
        }
        $temporary = $dialect === Dialect::PostgreSql ? 'pg_temp' : 'temp';
        $accepted = $dialect === Dialect::PostgreSql ? preg_match('/^pg_temp(_\d+)?$/D', $written ?? 'pg_temp') === 1 : strtolower($written ?? 'temp') === 'temp';
        if (!$accepted) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TemporaryTableSchema, $header);
        }
        return $written ?? $temporary;
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
        $inline = $this->identifiers->dialect === Dialect::MySql && \SqlSemantics\Model\Configuration\Replication\ReplicationRelease::number($this->grammarVersion) >= 90000 ? ['opt_references'] : [];
        foreach (Tree::outer($create, ['columnDef', 'column_def']) as $column) {
            $columns[] = [$column, array_values(array_filter(Tree::outer($column, ['ColConstraint', 'column_attribute', 'attribute', 'gcol_attribute', ...$inline]), Tree::hasTokens(...)))];
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
            $notNull = in_array($this->identifiers->dialect === Dialect::PostgreSql ? $column->name : strtolower($column->name), $primary, true) && $this->primaryNotNull($column, $primary, $constraints) || (in_array(strtolower($column->name), $primary, true) && $this->identifiers->dialect === Dialect::Sqlite && array_intersect(['WITHOUT ROWID', 'STRICT'], array_map(static fn (Node $option): string => strtoupper(Tree::text($option)), Tree::outer($source, ['table_option']))) !== []);
            $result[] = new ColumnDefinition($column->name, $column->type, $notNull ? Nullability::NotNull : $column->nullability, $column->source, $column->defaultExpression, $column->attributes, $column->generatedExpression, $column->options);
        }

        return $result;
    }

    /**
     * Carries a SQLite table-level PRIMARY KEY (column AUTOINCREMENT) to its column, as a column-level key would declare it.
     * @param list<ColumnDefinition> $columns
     * @param list<TableConstraint> $constraints
     * @return list<ColumnDefinition>
     * @throws \SqlSemantics\InvalidSql
     */
    public function autoIncrement(array $columns, array $constraints): array
    {
        foreach ($constraints as $constraint) {
            if ($constraint->kind !== ConstraintKind::PrimaryKey || $constraint->source->name !== 'tcons' || !Tree::hasTokens(Tree::outer($constraint->source, ['autoinc'])[0] ?? new Node('autoinc', 0, []))) {
                continue;
            }
            if (count($constraint->columns) !== 1) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AutoIncrementKey, $constraint->source);
            }
            $key = strtolower($constraint->columns[0]);
            $columns = array_map(static fn (ColumnDefinition $column): ColumnDefinition => strtolower($column->name) !== $key ? $column : new ColumnDefinition($column->name, $column->type, $column->nullability, $column->source, $column->defaultExpression, $column->attributes, $column->generatedExpression, [...$column->options, 'auto_increment' => true]), $columns);
        }
        return $columns;
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
            $words = Tree::keywords($constraint->source);
            if ($constraint->kind === ConstraintKind::PrimaryKey && $constraint->source->name === 'ccons' && in_array('DESC', ($words[0] ?? '') === 'CONSTRAINT' ? array_slice($words, 2) : $words, true)) {
                return false;
            }
        }

        return true;
    }
}
