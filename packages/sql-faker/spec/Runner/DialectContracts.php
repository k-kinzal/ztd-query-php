<?php

declare(strict_types=1);

namespace Spec\Runner;

use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotLoader;
use SqlFaker\Contract\StatementGenerator;
use SqlFaker\Contract\SupportedGrammarBuilder;
use SqlFaker\Contract\TerminalDeriver;
use SqlFaker\Contract\TerminalRenderer;
use SqlFaker\Contract\TerminationLengthComputer;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Contract\TokenJoiner;

final class DialectContracts
{
    public readonly StatementGenerator $statementGenerator;

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
        $this->statementGenerator = new class ($this) implements StatementGenerator {
            public function __construct(
                private readonly DialectContracts $contracts,
            ) {
            }

            public function generate(GenerationRequest $request): string
            {
                return $this->contracts->generate($request);
            }
        };
    }

    public function supportedGrammar(): Grammar
    {
        return $this->supportedGrammar ??= $this->supportedGrammarBuilder->build(
            $this->snapshotLoader->load(),
        );
    }

    public function terminationLengths(): TerminationLengths
    {
        return $this->terminationLengths ??= $this->terminationLengthComputer->compute(
            $this->supportedGrammar(),
        );
    }

    public function generate(GenerationRequest $request): string
    {
        $terminals = $this->terminalDeriver->derive(
            $this->supportedGrammar(),
            $this->terminationLengths(),
            $request,
        );
        $tokens = $this->terminalRenderer->render($terminals);

        return $this->tokenJoiner->join($tokens);
    }
}
