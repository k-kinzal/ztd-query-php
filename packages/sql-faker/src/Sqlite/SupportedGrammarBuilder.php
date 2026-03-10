<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SupportedGrammarBuilder as SupportedGrammarBuilderContract;
use SqlFaker\Grammar\ContractGrammarHydrator;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\NonTerminal;

final class SupportedGrammarBuilder implements SupportedGrammarBuilderContract
{
    public function build(Grammar $snapshot): Grammar
    {
        $faker = new FakerGenerator();

        return ContractGrammarProjector::project(
            (new SqlGenerator(
                ContractGrammarHydrator::hydrate($snapshot),
                $faker,
                new LexicalValueGenerator($faker),
            ))->compiledGrammar(),
            NonTerminal::class,
        );
    }
}
