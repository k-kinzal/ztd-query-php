<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\RandomSource;
use SqlFaker\Contract\TerminalDeriver as TerminalDeriverContract;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\TerminalDeriver as ContractTerminalDeriver;

final class TerminalDeriver implements TerminalDeriverContract
{
    private ContractTerminalDeriver $deriver;

    public function __construct(RandomSource $random)
    {
        $this->deriver = new ContractTerminalDeriver($random, 'cmd', true, true);
    }

    public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence
    {
        return $this->deriver->derive($grammar, $terminationLengths, $request);
    }
}
