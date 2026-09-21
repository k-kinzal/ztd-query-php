<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * A complete statement graph and the semantic problems encountered while resolving it.
 *
 * @example Reading analysis diagnostics
 *     $analysis = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->analyze('SELECT missing');
 *     $analysis->diagnostics[0]->reason // => 'unknown-column'
 *
 * @visibility public
 */
final class Analysis
{
    /**
     * @param BoundStatement $statement Structured statement, including unresolved references
     * @param list<Diagnostic> $diagnostics Name, type, and validity problems in encounter order
     */
    public function __construct(public readonly BoundStatement $statement, public readonly array $diagnostics)
    {
    }
}
