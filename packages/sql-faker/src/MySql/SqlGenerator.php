<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminalInventory;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;

/**
 * Derives MySQL parser terminals and realizes them through the versioned lexer model.
 */
final class SqlGenerator
{
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalGrammar $lexicalGrammar;
    private TerminationAnalyzer $terminationAnalyzer;

    /**
     * @param Grammar $grammar Compiled MySQL grammar to derive from
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
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            Grammar::resolveVersion($version),
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
        $startSymbol = $this->grammar->startSymbolFor($plan->startRule());
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $terminals = (new Derivation($this->grammar, $this->faker, $this->terminationAnalyzer))
                ->of($startSymbol, $plan);
            $terminalNames = (new ParserSemantics())->applied(array_map(
                static fn (Terminal $terminal): string => $terminal->value,
                $terminals,
            ));
            try {
                $sql = $this->lexicalGrammar->realize($terminalNames, $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = GenerationException::planRequiresNonEmptyOutput('MySQL');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? GenerationException::lexicalRealizationFailed('MySQL');
    }

}
