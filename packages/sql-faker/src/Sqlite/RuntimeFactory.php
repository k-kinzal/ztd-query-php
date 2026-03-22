<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\Generation\TerminationLengthComputer;
use SqlFaker\Generation\GenerationRuntime;

final class RuntimeFactory
{
    public static function build(
        FakerGenerator $faker,
        ?string $version = null,
        ?LexicalValueSource $lexicalValues = null,
    ): GenerationRuntime {
        $random = new FakerRandomSource($faker);
        $lexicalValues ??= new LexicalValueGenerator($random);

        return new GenerationRuntime(
            new SnapshotLoader($version),
            new SupportedGrammarBuilder(),
            new TerminationLengthComputer(),
            new TerminalDeriver($random),
            new TerminalRenderer($random, $lexicalValues),
            new TokenJoiner(),
        );
    }
}
