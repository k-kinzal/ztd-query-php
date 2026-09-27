<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use SqlSemantics\Core\Analysis\SchemaAnalyzer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Schema as SchemaState;

/**
 * Reads the table state needed to analyze statements against known declarations.
 *
 * Schema describes state; Semantics describes SQL operations. Statements whose
 * resulting columns require query evaluation remain statement models.
 *
 * @visibility public
 * @example Reading state for statement binding
 *     $schema = new \SqlSemantics\Facade\Schema(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $schema->analyze('CREATE TABLE t (id INTEGER)')->tables[0]->name // => 't'
 */
final class Schema
{
    private readonly SchemaAnalyzer $analyzer;

    /**
     * Selects the state declaration language, release and unqualified namespace.
     */
    public function __construct(Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null)
    {
        $this->analyzer = new SchemaAnalyzer($dialect, $defaultSchema, $grammarVersion);
    }

    /**
     * Reads table declarations and DROP TABLE resets into an independent state.
     * @throws \SqlSemantics\Core\SemanticException When catalog facts conflict or cannot be resolved
     * @throws \SqlSemantics\Core\AnalysisException When declarations are outside the selected language
     */
    public function analyze(string ...$sql): SchemaState
    {
        return $this->analyzer->analyze(...$sql);
    }
}
