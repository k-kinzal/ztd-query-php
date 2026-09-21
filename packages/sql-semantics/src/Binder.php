<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\BoundStatement;

/**
 * Performs the SQL semantic phase: name binding, type resolution, and NULL propagation.
 *
 * @example Binding SQL against a schema
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM users');
 *     $statement->outputs[0]->expression->type->name // => 'integer'
 *     $statement->outputs[0]->expression->nullability->value // => 'not-null'
 *
 * @visibility public
 */
final class Binder
{
    private readonly DialectParser $parser;

    /**
     * Uses the schema's dialect, grammar release, and default name resolution schema.
     */
    public function __construct(public readonly Schema $schema)
    {
        $this->parser = new DialectParser($schema->dialect, $schema->grammarVersion);
    }

    /**
     * Parses and binds one SQL statement into an immutable semantic representation.
     *
     * @param string $sql One SQL statement in the schema's dialect
     * @return BoundStatement Bound relations and expressions with types, NULL facts, and source syntax
     * @throws SemanticException When names or types cannot be resolved
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function bind(string $sql): BoundStatement
    {
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema);

        return (new Binding\Statement\StatementBinder($tables))->bind($this->parser->parse($sql));
    }
    /**
     * Analyzes the entire statement even when names or types cannot be resolved.
     *
     * Unresolved references retain their spelling and unknown type; diagnostics never
     * substitute for lowering an unimplemented syntax production.
     */
    public function analyze(string $sql): Model\Analysis
    {
        $diagnostics = new Binding\Analysis\Diagnostics(true);
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema, $diagnostics);
        $statement = (new Binding\Statement\StatementBinder($tables))->bind($this->parser->parse($sql));
        return new Model\Analysis($statement, $diagnostics->items);
    }

    /**
     * Replaces one owned expression and returns a freshly parsed, validated semantic graph.
     *
     * Parentheses preserve precedence. The fragment must be one expression; additional
     * clauses or statements are rejected. Name, type, and scope facts are rebound.
     */
    public function replaceExpression(BoundStatement $statement, Model\Expression $target, string $replacement): BoundStatement
    {
        $sql = (new Binding\Editing\ExpressionEdit())->sql($statement, $target, $replacement, $this->parser);
        return $this->bind($sql);
    }

    /**
     * Binds a script while preserving statement boundaries.
     *
     * @return list<BoundStatement> Statements in source order
     */
    public function bindAll(string $sql): array
    {
        $tree = $this->parser->parse($sql);
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema);
        $statements = Ast\StatementList::read($tree, $this->schema->dialect);
        return array_map(fn (\SqlParser\Parser\Node $statement): BoundStatement => (new Binding\Statement\StatementBinder($tables))->bind(new \SqlParser\Parser\Node($tree->name, $tree->ordinal, [$statement])), $statements);
    }

}
