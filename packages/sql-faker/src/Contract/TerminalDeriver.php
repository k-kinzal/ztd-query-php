<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface TerminalDeriver
{
    public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence;
}
