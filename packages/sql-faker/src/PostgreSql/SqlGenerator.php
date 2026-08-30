<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Model\TerminalInventory;
use SqlFaker\Grammar\Walk\Derivation;
use SqlFaker\Grammar\Walk\GenerationException;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\Grammar\Walk\TerminationAnalyzer;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\Lexical\LexicalGrammar;

/**
 * Derives PostgreSQL parser terminals and realizes them through the versioned lexer model.
 */
final class SqlGenerator
{
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalGrammar $lexicalGrammar;
    private TerminationAnalyzer $terminationAnalyzer;

    /**
     * @param Grammar $grammar Compiled PostgreSQL grammar to derive from
     * @param FakerGenerator $faker Source of every choice a derivation makes freely
     * @param string|null $version Release whose lexer the SQL must read back through, or null to accept synthetic terminals
     *
     * @throws LexicalCatalogException When the release's lexer cannot write a terminal the grammar declares
     * @throws RuntimeException When the release is not one this package ships
     */
    /**
     * Answers a generator over the grammar of one PostgreSQL version.
     *
     * Every provider needs the same three steps to get here -- resolve the
     * version, load its grammar, generate against it -- so they are written
     * once rather than in each provider's constructor.
     *
     * @param FakerGenerator $faker Source of the choices generation makes
     * @param string|null $version Version tag to generate for, or null for the default
     *
     * @return self A generator bound to that version's grammar
     */
    public static function for(FakerGenerator $faker, ?string $version = null): self
    {
        $resolved = PgGrammar::resolveVersion($version);

        return new self(PgGrammar::load($resolved), $faker, $resolved);
    }

    /**
     * Binds the generator to a grammar and the source of its choices.
     *
     * @param Grammar $grammar Grammar to walk
     * @param FakerGenerator $faker Source of the choices generation makes
     * @param string|null $version Version tag the grammar came from, or null for the default
     */
    public function __construct(
        Grammar $grammar,
        FakerGenerator $faker,
        ?string $version = null,
    ) {
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            PgGrammar::resolveVersion($version),
            $version === null,
        );
        if ($version !== null) {
            $this->lexicalGrammar->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->terminationAnalyzer = new TerminationAnalyzer($grammar, $this->lexicalGrammar->supports(...));
    }

    /**
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    public function generate(GenerationPlan $plan): string
    {
        if ($plan->lexicalTarget() !== null) {
            return $this->lexicalGrammar->generate($plan);
        }
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $terminals = (new Derivation($this->grammar, $this->faker, $this->terminationAnalyzer))
                ->of($plan->startRule() ?? 'stmtmulti', $plan);
            $terminalNames = (new ParserSemantics($this->lexicalGrammar))->applied(array_map(
                static fn (Terminal $terminal): string => $terminal->value,
                $terminals,
            ));
            try {
                $sql = $this->lexicalGrammar->realize($terminalNames, $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = GenerationException::planRequiresNonEmptyOutput('PostgreSQL');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? GenerationException::lexicalRealizationFailed('PostgreSQL');
    }

}
