<?php

declare(strict_types=1);

namespace Spec\Adapter;

use Spec\Runner\DialectRuntime;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\GenerationRuntime;

final class GenerationRuntimeAdapter implements DialectRuntime
{
    public function __construct(
        private readonly GenerationRuntime $runtime,
    ) {
    }

    public function snapshot(): Grammar
    {
        return $this->runtime->snapshot();
    }

    public function supportedGrammar(): Grammar
    {
        return $this->runtime->supportedGrammar();
    }

    public function version(): string
    {
        return $this->runtime->version();
    }

    public function rewriteProgram(): RewriteProgram
    {
        return $this->runtime->rewriteProgram();
    }

    public function terminationLengths(): TerminationLengths
    {
        return $this->runtime->terminationLengths();
    }

    public function derive(GenerationRequest $request): TerminalSequence
    {
        return $this->runtime->derive($request);
    }

    public function generate(GenerationRequest $request): string
    {
        return $this->runtime->generate($request);
    }
}
