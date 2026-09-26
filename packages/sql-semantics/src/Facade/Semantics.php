<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Statement\Statement;

/**
 * Structures every statement of a selected SQL language into independent values.
 *
 * This entry point needs no schema or database connection. Use Binder separately
 * when schema-dependent name, type, and nullability facts are needed.
 *
 * @visibility public
 * @example Accepting an analyzer configured by a database package
 *     $analyze = static fn (\SqlSemantics\Facade\Semantics $semantics): \SqlSemantics\Statement\Statement => $semantics->analyze('SELECT 1');
 *     $analyze instanceof \Closure // => true
 */
final class Semantics
{
    private readonly Analyzer $analyzer;

    /**
     * Selects the dialect and optionally one of its shipped grammar releases.
     */
    public function __construct(Dialect $dialect, ?string $grammarVersion = null)
    {
        $this->analyzer = new Analyzer($dialect, $grammarVersion);
    }

    /**
     * Builds an immutable statement from SQL without keeping its original syntax.
     *
     * @throws \SqlSemantics\Core\AnalysisException When SQL is not in the selected language
     */
    public function analyze(string $sql): Statement
    {
        return $this->analyzer->analyze($sql);
    }
}
