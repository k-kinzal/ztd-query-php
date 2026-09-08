<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Closure;
use Faker\Generator;
use RuntimeException;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalGrammar as LexicalContract;
use SqlFaker\Sqlite\Generation\Rewrite\RewriteDefinitions;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

/**
 * Binds the Sqlite grammar and lexical profile to the common SQL generation engine.
 *
 * @visibility root
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
     * Declared structural transformations for this dialect.
     */
    public readonly TokenRewriter $rewriter;

    /**
     * @var (Closure(string|null): string)|null
     */
    public readonly ?Closure $startSymbol;

    /**
     * Prepares dialect inputs for the common generator.
     *
     * @param Grammar $grammar Common AST for this release
     * @param Generator $faker Source of random lexical choices
     * @param string|null $version Exact release or null for the declared default
     * @throws RuntimeException When the requested release is unavailable
     */
    public function __construct(Grammar $grammar, Generator $faker, ?string $version = null)
    {
        $lexical = new LexicalGrammar($faker, SqliteGrammar::resolveVersion($version));
        $this->grammar = (new GrammarAdaptation())->adapted($grammar);
        $this->rewriter = (new RewriteDefinitions())->create();
        $this->startSymbol = null;
        $this->lexicalGrammar = $lexical;
    }
}
