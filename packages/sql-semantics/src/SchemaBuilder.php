<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;

/**
 * Constructs schema declarations and their resolution context from CREATE TABLE SQL.
 *
 * @example Building a schema from SQL strings
 *     $builder = new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql);
 *     $schema = $builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY)');
 *     $schema->tables[0]->name // => 'users'
 *     $schema->defaultSchema // => 'public'
 *
 * @visibility public
 */
final class SchemaBuilder
{
    private readonly DialectParser $parser;

    /**
     * Namespace used for unqualified table declarations and references.
     */
    public readonly string $defaultSchema;

    /**
     * @param Dialect $dialect Language used for declarations and subsequent statement binding
     * @param string|null $defaultSchema Defaults to public, main, or an unnamed MySQL database
     * @param string|null $grammarVersion sql-parser release tag; null selects its default release
     */
    public function __construct(public readonly Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null)
    {
        $this->defaultSchema = $defaultSchema ?? match ($dialect) {
            Dialect::PostgreSql => 'public',
            Dialect::MySql => '',
            Dialect::Sqlite => 'main',
        };
        $this->parser = new DialectParser($dialect, $grammarVersion);
    }

    /**
     * Builds an independent schema from zero or more SQL strings containing CREATE TABLE statements.
     *
     * @param string ...$sql DDL strings in declaration order; no arguments builds an empty schema
     * @throws SemanticException When declarations conflict or use unsupported semantics
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function build(string ...$sql): Schema
    {
        $trees = array_map(fn (string $text): Node => $this->parser->parse($text), array_values($sql));
        $tables = (new SchemaReader(new Identifiers($this->dialect), $this->defaultSchema))->read($trees);

        return new Schema($this->dialect, $tables, $this->defaultSchema, $this->parser->version());
    }
}
