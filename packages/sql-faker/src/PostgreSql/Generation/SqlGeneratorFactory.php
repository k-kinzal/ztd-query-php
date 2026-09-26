<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation;

use Faker\Generator;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Model\Grammar;

/**
 * Composes the PostgreSql implementation with the common generation engine.
 *
 * @visibility root
 */
final class SqlGeneratorFactory
{
    /**
     * Binds a release's grammar and lexical behavior to the generator.
     */
    public static function create(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $grammar = $grammar->identified();
        $context = new GenerationContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $coverage,
            $grammar,
        );
    }
}
