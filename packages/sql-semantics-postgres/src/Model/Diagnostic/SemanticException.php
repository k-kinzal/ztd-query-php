<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Diagnostic;

use RuntimeException;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;

/**
 * Semantic problems established while analyzing grammatical SQL.
 *
 * @visibility public
 * @example Rejecting a query that names a missing relation
 *     (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT * FROM t', []) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException
 */
final class SemanticException extends RuntimeException implements \SqlSemantics\AnalysisException
{
    /**
     * @param non-empty-list<Diagnostic> $diagnostics The problems in derivation order
     */
    public function __construct(public readonly array $diagnostics)
    {
        parent::__construct(implode("\n", array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $diagnostics)));
    }
}
