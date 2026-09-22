<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Diagnostic;
use SqlSemantics\SemanticException;

/**
 * Collects semantic diagnostics during analysis or raises them during strict binding.
 *
 * @visibility SqlSemantics
 */
final class Diagnostics
{
    /**
     * @var list<Diagnostic>
     */
    public array $items = [];

    /**
     * Enables analysis of SQL whose schema definitions or semantic validity are incomplete.
     */
    public function __construct(public readonly bool $collect = false)
    {
    }

    /**
     * Reports an expected semantic problem. Internal lowering failures are never recoverable.
     *
     * @throws SemanticException
     */
    public function report(string $reason, string $message, Node|Token $source): void
    {
        if (!$this->collect) {
            throw new SemanticException($reason, $message, $source);
        }
        $this->items[] = new Diagnostic($reason, $message, $source);
    }
}
