<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Generation\SqlGenerator as CommonSqlGenerator;
use SqlFaker\Grammar\Derivation;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\Terminal;

/**
 * Derives PostgreSQL parser terminals and realizes them through the versioned lexer model.
 */
final class SqlGenerator
{
    private CommonSqlGenerator $generator;

    /**
     * @param Grammar $grammar Compiled PostgreSQL grammar to derive from
     * @param FakerGenerator $faker Source of every choice a derivation makes freely
     * @param string|null $version Release whose lexer the SQL must read back through, or null to accept synthetic terminals
     *
     * @throws LexicalCatalogException When the release's lexer cannot write a terminal the grammar declares
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
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    public function generate(GenerationPlan $plan): string
    {
        return $this->generator->generate($plan);
    }
}
