<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use RuntimeException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * An explicit semantic failure; callers must not treat a rejected tree as bound.
 *
 * @example Reading semantic facts
 *     $source = new \SqlParser\Parser\Node('expr', 0, []);
 *     $error = new \SqlSemantics\Core\SemanticException('unknown-column', 'Missing column', $source);
 *     $error->reason // => 'unknown-column'
 *
 * @visibility public
 */
final class SemanticException extends RuntimeException
{
    /**
     * @param string $reason Stable machine-readable failure code
     * @param string $message Human-readable explanation
     * @param Node|Token $source Original syntax responsible for the failure
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly Node|Token $source,
    ) {
        parent::__construct($message);
    }
}
