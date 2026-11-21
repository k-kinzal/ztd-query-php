<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Diagnostic;

use RuntimeException;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;

/**
 * Semantic problems established while analyzing grammatical SQL.
 *
 * @visibility public
 * @example Rejecting a query that names a missing relation
 *     (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT * FROM t', []) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException
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
