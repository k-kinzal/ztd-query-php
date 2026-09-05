<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Generation\SqlGenerator as CommonSqlGenerator;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;

/**
 * Grammar-driven SQL generator for SQLite.
 *
 * Generates syntactically valid SQL strings using SQLite's official Lemon grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
 */
final class SqlGenerator
{
    private CommonSqlGenerator $generator;

    /**
     * @param Grammar $grammar Compiled SQLite grammar to derive from
     * @param FakerGenerator $faker Source of every choice a derivation makes freely
     * @param string|null $version Release whose tokenizer the SQL must read back through, or null to accept synthetic terminals
     *
     * @throws LexicalCatalogException When the release's tokenizer cannot write a terminal the grammar declares
     * @throws RuntimeException When the release is not one this package ships
     */
    public function __construct(
        Grammar $grammar,
        FakerGenerator $faker,
        ?string $version = null,
    ) {
        $context = new GenerationContext($grammar, $faker, $version);
        $this->generator = new CommonSqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->normalize,
            $context->startSymbol,
        );
    }

    /**
     * Generate a syntactically valid SQL string.
     *
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan Grammar generation range and production constraints
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    public function generate(GenerationPlan $plan): string
    {
        return $this->generator->generate($plan->withStepBudget());
    }
}
