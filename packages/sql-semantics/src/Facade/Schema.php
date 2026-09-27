<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use InvalidArgumentException;
use SqlSemantics\Core\Analysis\SchemaAnalyzer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Schema as SchemaState;

/**
 * Reads the declared table state that statements are analyzed against.
 *
 * Schema describes state as declarations spell it; Semantics describes SQL
 * operations. A statement that changes state, such as ALTER TABLE, or whose
 * resulting columns need query evaluation, such as CREATE TABLE AS SELECT,
 * is a statement model of Semantics, and this reader does not evaluate it.
 *
 * @visibility public
 * @example Reading state for statement analysis
 *     $schema = new \SqlSemantics\Facade\Schema(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $schema->analyze('CREATE TABLE t (id INTEGER)')->tables[0]->name // => 't'
 */
final class Schema
{
    private readonly SchemaAnalyzer $analyzer;

    /**
     * Selects the declaration language, its unqualified namespace, and optionally a release, a mode, and a parameter syntax.
     *
     * @param Dialect $dialect The database
     * @param string|null $defaultSchema The namespace of unqualified declarations, or null for the database's default
     * @param string|null $grammarVersion A release tag the dialect ships, or null for its default
     * @param Mode|null $mode The session settings declarations are read under, or null for the server's defaults
     * @param Parameters $parameters Which parameter markers are read
     *
     * @throws InvalidArgumentException When the mode does not belong to the dialect
     */
    public function __construct(Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null, ?Mode $mode = null, Parameters $parameters = Parameters::Native)
    {
        $this->analyzer = new SchemaAnalyzer(new Language($dialect, $grammarVersion, $mode, $parameters), $defaultSchema);
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
