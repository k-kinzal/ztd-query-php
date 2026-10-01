<?php

declare(strict_types=1);

namespace Tests\Scenario;

use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Binding\SelectBinder;
use SqlSemantics\Core\Binding\TableResolver;
use SqlSemantics\Core\Model\BoundSelect;
use SqlSemantics\Core\Schema;

/**
 * Exercises the preexisting internal scalar and scope rules during migration.
 *
 * @visibility Tests
 */
final class BindingHarness
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
     * @throws \SqlSemantics\Core\SemanticException When names, types, or supported semantics cannot be resolved
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the selected grammar
     */
    public function bind(string $sql): BoundSelect
    {
        $tables = new TableResolver($this->schema, new Identifiers($this->schema->dialect), $this->schema->defaultSchema);

        return (new SelectBinder($tables))->bind($this->parser->parse($sql));
    }
}
