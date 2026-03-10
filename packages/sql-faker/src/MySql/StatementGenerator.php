<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\SnapshotLoader as SnapshotLoaderContract;
use SqlFaker\Contract\StatementGenerator as StatementGeneratorContract;
use SqlFaker\Contract\SupportedGrammarBuilder as SupportedGrammarBuilderContract;
use SqlFaker\Contract\TerminalDeriver as TerminalDeriverContract;
use SqlFaker\Contract\TerminalRenderer as TerminalRendererContract;
use SqlFaker\Contract\TerminationLengthComputer as TerminationLengthComputerContract;
use SqlFaker\Contract\TokenJoiner as TokenJoinerContract;
use SqlFaker\Generation\TerminationLengthComputer as ContractTerminationLengthComputer;

final class StatementGenerator implements StatementGeneratorContract
{
    private SnapshotLoaderContract $snapshotLoader;
    private SupportedGrammarBuilderContract $supportedGrammarBuilder;
    private TerminationLengthComputerContract $terminationLengthComputer;
    private TerminalDeriverContract $terminalDeriver;
    private TerminalRendererContract $terminalRenderer;
    private TokenJoinerContract $tokenJoiner;

    private ?\SqlFaker\Contract\Grammar $supportedGrammar = null;
    private ?\SqlFaker\Contract\TerminationLengths $terminationLengths = null;

    public function __construct(
        FakerGenerator $faker,
        ?string $version = null,
        ?LexicalValueSource $lexicalValues = null,
        ?SnapshotLoaderContract $snapshotLoader = null,
        ?SupportedGrammarBuilderContract $supportedGrammarBuilder = null,
        ?TerminationLengthComputerContract $terminationLengthComputer = null,
        ?TerminalDeriverContract $terminalDeriver = null,
        ?TerminalRendererContract $terminalRenderer = null,
        ?TokenJoinerContract $tokenJoiner = null,
    ) {
        $lexicalValues ??= new LexicalValueGenerator($faker);

        $this->snapshotLoader = $snapshotLoader ?? new SnapshotLoader($version);
        $this->supportedGrammarBuilder = $supportedGrammarBuilder ?? new SupportedGrammarBuilder();
        $this->terminationLengthComputer = $terminationLengthComputer ?? new ContractTerminationLengthComputer();
        $this->terminalDeriver = $terminalDeriver ?? new TerminalDeriver($faker);
        $this->terminalRenderer = $terminalRenderer ?? new TerminalRenderer($faker, $lexicalValues);
        $this->tokenJoiner = $tokenJoiner ?? new TokenJoiner();
    }

    public function generate(GenerationRequest $request): string
    {
        $terminals = $this->terminalDeriver->derive($this->supportedGrammar(), $this->terminationLengths(), $request);
        $tokens = $this->terminalRenderer->render($terminals);

        return $this->tokenJoiner->join($tokens);
    }

    private function supportedGrammar(): \SqlFaker\Contract\Grammar
    {
        return $this->supportedGrammar ??= $this->supportedGrammarBuilder->build(
            $this->snapshotLoader->load(),
        );
    }

    private function terminationLengths(): \SqlFaker\Contract\TerminationLengths
    {
        return $this->terminationLengths ??= $this->terminationLengthComputer->compute(
            $this->supportedGrammar(),
        );
    }
}
