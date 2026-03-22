<?php

declare(strict_types=1);

namespace Spec\Runner;

use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;

interface DialectRuntime
{
    public function snapshot(): Grammar;

    public function supportedGrammar(): Grammar;

    public function version(): string;

    public function rewriteProgram(): RewriteProgram;

    public function terminationLengths(): TerminationLengths;

    public function derive(GenerationRequest $request): TerminalSequence;

    public function generate(GenerationRequest $request): string;
}
