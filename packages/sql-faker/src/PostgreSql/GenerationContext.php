<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Closure;
use Faker\Generator;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Lexical\TerminalInventory;
use SqlFaker\Grammar\LexicalGrammar as LexicalContract;
use SqlFaker\PostgreSql\Grammar\PgGrammar;

/**
 * Binds the PostgreSql grammar and lexical profile to the common SQL generation engine.
 */
final class GenerationContext
{
    /**
     * Common grammar prepared for generation.
     */
    public readonly Grammar $grammar;

    /**
     * Lexical realization for this release.
     */
    public readonly LexicalContract $lexicalGrammar;

    /**
     * @var (Closure(list<string>): list<string>)|null
     */
    public readonly ?Closure $normalize;

    /**
     * @var (Closure(string|null): string)|null
     */
    public readonly ?Closure $startSymbol;

    /**
     * Prepares dialect inputs for the common generator.
     *
     * @param Grammar $grammar Common AST for this release
     * @param Generator $faker Source of random lexical choices
     * @param string|null $version Exact release or null for synthetic terminals
     * @throws \SqlFaker\Grammar\LexicalCatalogException When a grammar terminal has no lexical witness
     * @throws RuntimeException When the requested release is unavailable
     */
    public function __construct(Grammar $grammar, Generator $faker, ?string $version = null)
    {
        $lexical = new LexicalGrammar($faker, PgGrammar::resolveVersion($version), $version === null);
        $this->grammar = new Grammar('stmtmulti', $grammar->ruleMap);
        $this->normalize = (new ParserSemantics($lexical))->applied(...);
        $this->startSymbol = null;
        if ($version !== null) {
            $lexical->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->lexicalGrammar = $lexical;
    }
}
