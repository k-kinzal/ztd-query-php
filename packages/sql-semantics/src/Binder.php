<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\SelectBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\BoundSelect;

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
     * Parses SQL and binds one SELECT into an immutable semantic representation.
     *
     * @param string $sql One SELECT statement in the schema's dialect
     * @return BoundSelect Bound relations and expressions with types, NULL facts, and source syntax
     * @throws SemanticException When names, types, or supported semantics cannot be resolved
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function bind(string $sql): BoundSelect
    {
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema);

        return (new SelectBinder($tables))->bind($this->parser->parse($sql));
    }
}
