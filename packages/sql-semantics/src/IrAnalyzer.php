<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * Publishes complete semantic statements for a database implementation.
 *
 * @visibility public
 * @example Consuming a dialect implementation through the interface
 *     $analyze = static fn (\SqlSemantics\IrAnalyzer $analyzer, string $sql): \SqlSemantics\SemanticsIR => $analyzer->analyze($sql);
 *     is_callable($analyze) // => true
 */
interface IrAnalyzer extends Analyzer
{
    /**
     * Resolves the statement before publishing it.
     *
     * @throws AnalysisException When the SQL is invalid, unresolved or outside the implemented IR domain
     */
    public function analyze(string $sql, ?AnalysisContext $context = null): SemanticsIR;
}
