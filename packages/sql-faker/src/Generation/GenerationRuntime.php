<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\SnapshotLoader;
use SqlFaker\Contract\StatementGenerator;
use SqlFaker\Contract\SupportedGrammarBuilder;
use SqlFaker\Contract\TerminalDeriver;
use SqlFaker\Contract\TerminalRenderer;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengthComputer;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Contract\TokenJoiner;

final class GenerationRuntime implements StatementGenerator
{
    private ?Grammar $snapshot = null;
    private ?Grammar $supportedGrammar = null;
    private ?TerminationLengths $terminationLengths = null;

    public function __construct(
        public readonly SnapshotLoader $snapshotLoader,
        public readonly SupportedGrammarBuilder $supportedGrammarBuilder,
        public readonly TerminationLengthComputer $terminationLengthComputer,
        public readonly TerminalDeriver $terminalDeriver,
        public readonly TerminalRenderer $terminalRenderer,
        public readonly TokenJoiner $tokenJoiner,
    ) {
    }

    public function snapshot(): Grammar
    {
        return $this->snapshot ??= $this->snapshotLoader->load();
    }

    public function version(): string
    {
        return $this->snapshotLoader->version();
    }

    public function supportedGrammar(): Grammar
    {
        return $this->supportedGrammar ??= $this->supportedGrammarBuilder->build(
            $this->snapshot(),
        );
    }

    public function rewriteProgram(): RewriteProgram
    {
        return $this->supportedGrammarBuilder->rewriteProgram();
    }

    public function terminationLengths(): TerminationLengths
    {
        return $this->terminationLengths ??= $this->terminationLengthComputer->compute(
            $this->supportedGrammar(),
        );
    }

    public function derive(GenerationRequest $request): TerminalSequence
    {
        return $this->terminalDeriver->derive(
            $this->supportedGrammar(),
            $this->terminationLengths(),
            $request,
        );
    }

    public function generate(GenerationRequest $request): string
    {
        $terminals = $this->derive($request);
        $tokens = $this->terminalRenderer->render($terminals);

        return $this->tokenJoiner->join($tokens);
    }
}
