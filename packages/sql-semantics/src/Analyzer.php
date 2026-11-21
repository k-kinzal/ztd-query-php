<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * Publishes complete SQL semantics under a database-specific language profile.
 *
 * @visibility public
 * @example Accepting an analyzer from any database package
 *     $render = static fn (\SqlSemantics\Analyzer $analyzer, string $sql): string => $analyzer->analyze($sql)->toString();
 *     is_callable($render) // => true
 */
interface Analyzer
{
    /**
     * Resolves SQL against the supplied declarations and publishes a complete statement.
     *
     * @throws AnalysisException When the SQL is invalid, unresolved, or outside the supported language profile
     */
    public function analyze(string $sql, ?AnalysisContext $context = null): SemanticsIR;
}
