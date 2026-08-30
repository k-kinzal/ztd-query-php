<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Model\TerminalInventory;
use SqlFaker\Grammar\Walk\GenerationException;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\Grammar\Walk\TerminationAnalyzer;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\Lexical\LexicalGrammar;

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
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalGrammar $lexicalGrammar;
    private TerminationAnalyzer $terminationAnalyzer;

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
        $this->grammar = (new GrammarAdaptation())->adapted($grammar);
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            SqliteGrammar::resolveVersion($version),
            $version === null,
        );
        if ($version !== null) {
            $this->lexicalGrammar->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->terminationAnalyzer = new TerminationAnalyzer(
            $this->grammar,
            $this->lexicalGrammar->supports(...),
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
        if ($plan->lexicalTarget() !== null) {
            return $this->lexicalGrammar->generate($plan);
        }
        $start = $plan->startRule() ?? 'cmd';
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $terminals = (new Derivation($this->grammar, $this->faker, $this->terminationAnalyzer))
                ->of($start, $plan);
            try {
                $sql = $this->lexicalGrammar->realize(array_map(
                    static fn (Terminal $terminal): string => $terminal->value,
                    $terminals,
                ), $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = GenerationException::planRequiresNonEmptyOutput('SQLite');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? GenerationException::lexicalRealizationFailed('SQLite');
    }

}
