<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * A semantic problem tied to its original syntax, without discarding the statement graph.
 *
 * @example Reading analysis diagnostics
 *     $analysis = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->analyze('SELECT missing');
 *     $analysis->diagnostics[0]->reason // => 'unknown-column'
 *
 * @visibility public
 */
final class Diagnostic
{
    /**
     * @param string $reason Machine-readable semantic problem
     * @param string $message Explanation of the unresolved or invalid semantic fact
     * @param Node|Token $source Syntax responsible for this diagnostic
     */
    public function __construct(public readonly string $reason, public readonly string $message, public readonly Node|Token $source)
    {
    }
}
