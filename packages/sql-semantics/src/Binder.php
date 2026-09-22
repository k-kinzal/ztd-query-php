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
     * @param bool $strict Raise semantic errors; false retains them in the returned statement's diagnostics
     * @return BoundStatement Bound relations and expressions with types, NULL facts, and source syntax
     * @throws SemanticException When strict binding encounters a semantic error
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function bind(string $sql, bool $strict = true): BoundStatement
    {
        $diagnostics = new Binding\Analysis\Diagnostics(!$strict);
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema, $diagnostics);
        return (new Binding\Statement\StatementBinder($tables))->bind($this->parser->parse($sql))->withContext(new Binding\Editing\StatementContext($this->schema));
    }

    /**
     * Binds a script while preserving statement boundaries.
     *
     * @param bool $strict Raise semantic errors; false collects diagnostics separately on each statement
     * @return list<BoundStatement> Statements in source order
     * @throws SemanticException When strict binding encounters a semantic error
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function bindAll(string $sql, bool $strict = true): array
    {
        $tree = $this->parser->parse($sql);
        $statements = Ast\StatementList::read($tree, $this->schema->dialect);
        return array_map(function (\SqlParser\Parser\Node $statement) use ($tree, $strict): BoundStatement {
            $diagnostics = new Binding\Analysis\Diagnostics(!$strict);
            $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema, $diagnostics);
            return (new Binding\Statement\StatementBinder($tables))->bind(new \SqlParser\Parser\Node($tree->name, $tree->ordinal, [$statement]))->withContext(new Binding\Editing\StatementContext($this->schema));
        }, $statements);
    }

}
