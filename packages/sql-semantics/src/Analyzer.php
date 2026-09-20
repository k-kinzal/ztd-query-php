<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlParser\Parser\Node;
use SqlSemantics\Analysis\SelectReader;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\SelectQuery;
use SqlSemantics\Schema\Catalog;

/**
 * Binds sql-parser trees against explicit declarations without connecting to a database.
 *
 * @example Analyzing a PostgreSQL projection
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY)'));
 *     $query = $analyzer->analyze($parser->parse('SELECT id FROM users'), $catalog);
 *     $query->outputs[0]->expression->type->name // => 'integer'
 *     $query->outputs[0]->expression->nullability->value // => 'not-null'
 *
 * @visibility public
 */
final class Analyzer
{
    /**
     * Schema used to resolve unqualified table references.
     */
    public readonly string $defaultSchema;

    /**
     * Selects semantic rules and the schema used for unqualified table names.
     *
     * @param Dialect $dialect Parser dialect
     * @param string|null $defaultSchema Explicit resolution context; defaults to public, main, or an unnamed MySQL database
     */
    public function __construct(public readonly Dialect $dialect, ?string $defaultSchema = null)
    {
        $this->defaultSchema = $defaultSchema ?? match ($dialect) {
            Dialect::PostgreSql => 'public',
            Dialect::MySql => '',
            Dialect::Sqlite => 'main',
        };
    }

    /**
     * Builds a closed catalog from CREATE TABLE trees.
     *
     * @param Node ...$trees Parsed declaration roots from the selected dialect
     * @return Catalog Ordered declarations and integrity constraints
     */
    public function schema(Node ...$trees): Catalog
    {
        return (new SchemaReader(new Identifiers($this->dialect), $this->defaultSchema))->read(array_values($trees));
    }

    /**
     * Resolves one supported SELECT tree, preserving every source node.
     *
     * @param Node $tree Parser root for one SELECT statement
     * @param Catalog $catalog Available declarations
     * @return SelectQuery Logical query with typed and bound expressions
     * @throws SemanticException If names, types, syntax, or dialect cannot be resolved safely
     */
    public function analyze(Node $tree, Catalog $catalog): SelectQuery
    {
        if ($catalog->dialect !== $this->dialect) {
            throw new SemanticException('dialect-mismatch', 'The catalog and query dialects differ.', $tree);
        }

        return (new SelectReader(new TableResolver($catalog, new Identifiers($this->dialect), $this->defaultSchema)))->read($tree);
    }
}
