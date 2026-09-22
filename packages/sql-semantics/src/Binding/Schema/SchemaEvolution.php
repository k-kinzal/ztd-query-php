<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Ast\StatementList;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryRelation;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Schema;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SemanticException;

/**
 * Applies schema declarations in order, retaining every original DDL statement.
 *
 * @visibility SqlSemantics
 */
final class SchemaEvolution
{
    /**
     * Reads DDL in the same language context used for query binding.
     */
    public function __construct(public readonly Schema $initial)
    {
    }

    /**
     * @param list<Node> $trees
     *
     * @throws SemanticException
     */
    public function build(array $trees): Schema
    {
        $schema = $this->initial;
        foreach ($trees as $tree) {
            foreach (StatementList::read($tree, $schema->dialect) as $statement) {
                $tables = $this->apply($schema, $statement);
                $schema = new Schema($schema->dialect, $tables, $schema->defaultSchema, $schema->grammarVersion, [...$schema->statements, $statement], $schema->functions, $schema->variables);
            }
        }
        return $schema;
    }

    /**
     * @return list<TableDefinition>
     * @throws SemanticException
     */
    public function apply(Schema $schema, Node $statement): array
    {
        $identifiers = new Identifiers($schema->dialect);
        $resolver = new TableResolver($schema, $identifiers, $schema->defaultSchema);
        $text = strtoupper(Tree::text($statement));
        if (preg_match('/^CREATE (UNIQUE |FULLTEXT |SPATIAL )?INDEX /', $text) === 1) {
            return (new IndexEvolution($resolver))->apply($statement);
        }
        $create = Tree::outer($statement, ['CreateStmt', 'CreateAsStmt', 'ViewStmt', 'create', 'create_table_stmt', 'view_tail', 'create_table'])[0] ?? null;
        if ($create !== null || preg_match('/^CREATE (TEMP |TEMPORARY )?VIEW /', $text) === 1) {
            $table = $this->create($schema, $statement, $create ?? $statement, $resolver);
            $tables = $schema->tables;
            foreach ($tables as $index => $existing) {
                if ($identifiers->relationEqual($existing->name, $table->name) && $identifiers->relationEqual($existing->schema, $table->schema)) {
                    if (str_contains($text, 'IF NOT EXISTS')) {
                        return $tables;
                    }
                    if (!str_contains($text, 'OR REPLACE')) {
                        throw new SemanticException('duplicate-table', 'Duplicate table declaration: ' . $table->name, $statement);
                    }
                    $tables[$index] = $table;
                    return $tables;
                }
            }
            return [...$tables, $table];
        }
        if (str_starts_with($text, 'ALTER TABLE ')) {
            return (new TableAlteration($resolver))->apply($statement);
        }
        if (preg_match('/^DROP (TEMPORARY )?(TABLE|VIEW) /', $text) === 1) {
            $names = Tree::outer($statement, ['any_name', 'table_ident', 'fullname']);
            $drop = array_map(static fn (Node $name): string => implode('.', $identifiers->parts($name)), $names);
            return array_values(array_filter($schema->tables, static fn (TableDefinition $table): bool => !in_array($table->name, $drop, true) && !in_array($table->schema . '.' . $table->name, $drop, true)));
        }
        return (new IndexEvolution($resolver))->apply($statement);
    }

    /**
     * Resolves declared columns, copied declarations, inheritance, and AS queries.
     *
     * @throws SemanticException
     */
    public function create(Schema $schema, Node $statement, Node $create, TableResolver $resolver): TableDefinition
    {
        $reader = new SchemaReader($resolver->identifiers, $schema->defaultSchema);
        $source = $schema->dialect === Dialect::Sqlite ? $statement : $create;
        $names = Tree::outer($create, ['qualified_name', 'table_ident', 'nm']);
        $queryNode = Tree::outer($source, ['SelectStmt', 'select_stmt', 'query_expression', 'select'])[0] ?? null;
        $table = DeclarationBinder::bind($reader->table($source), new QueryContext($resolver));
        $namespace = $table->schema;
        $name = $table->name;
        $columns = $table->columns;
        $constraints = $table->constraints;
        $copies = Tree::outer($source, ['TableLikeClause', 'OptInherit']);
        if (str_contains(strtoupper(Tree::text($source)), ' LIKE ') && $copies === [] && isset($names[1])) {
            $copies = [$names[1]];
        }
        foreach ($copies as $copy) {
            foreach (Tree::outer($copy, ['qualified_name', 'table_ident']) as $reference) {
                $base = $resolver->resolve($resolver->identifiers->parts($reference), $reference);
                array_push($columns, ...$base->columns);
                array_push($constraints, ...$base->constraints);
            }
        }
        if ($queryNode !== null) {
            $query = (new QueryContext($resolver))->bind($queryNode);
            $aliasNode = Tree::outer($source, ['opt_column_list', 'opt_derived_column_list', 'eidlist_opt'])[0] ?? null;
            $aliases = $aliasNode === null ? [] : array_values(array_filter($resolver->identifiers->parts($aliasNode), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
            $derived = QueryRelation::declaration($query, $name, $aliases, $source);
            $columns = [...$columns, ...$derived->columns];
        }
        return new TableDefinition($namespace, $name, $columns, $constraints, $source, indexes: $table->indexes, properties: $table->properties);
    }
}
