<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Per-generation input; dialect, version and registration tables belong to the generators.
 */
final class LexemeInput
{
    /**
     * Binds the current occurrence, selected suffix and optional planned spelling.
     */
    public function __construct(
        public readonly TerminalSequence $terminals,
        public readonly int $index,
        public readonly ResolvedOutput $right,
        public readonly ?string $requested = null,
    ) {
    }

    /**
     * Returns the terminal occurrence for which candidates are being evaluated.
     */
    public function terminal(): TerminalOccurrence
    {
        return $this->terminals->terminals[$this->index];
    }
}
