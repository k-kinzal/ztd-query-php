<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * Analyzes SQL under a database-specific language profile.
 *
 * @visibility public
 * @example Accepting an analyzer from any database package
 *     $render = static fn (\SqlSemantics\Analyzer $analyzer, string $sql): string => $analyzer->analyze($sql)->toString();
 *     is_callable($render) // => true
 */
interface Analyzer
{
    /**
     * Analyzes SQL against the supplied declaration context or an open context.
     *
     * @throws AnalysisException When the SQL is rejected or the context is incompatible
     */
    public function analyze(string $sql, ?AnalysisContext $context = null): Query;
}
