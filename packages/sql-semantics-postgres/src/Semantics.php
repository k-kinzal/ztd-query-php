<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Analyzer;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Catalog;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Splitter;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;

/**
 * Binds SQL under a fixed dialect profile and publishes complete semantic values.
 *
 * @visibility public
 * @example Analyzing and rendering a projection
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('SELECT 42 AS answer')->profileId() === $semantics->analyze('SELECT 1')->profileId() // => true
 */
final class Semantics implements Analyzer
{
    private readonly LanguageProfile $profile;

    private readonly SqlParser $parser;

    private readonly Catalog $catalog;

    private readonly StatementBinder $binder;

    /**
     * Fixes the language profile: database, grammar release, session mode and parameter style.
     *
     * @param string|null $grammarVersion A release tag the database package ships, or null for its default
     * @param Mode|null $mode The session settings SQL is read under, or null for the defaults of the database
     * @param ParameterStyle $parameters Which parameter markers are read; the named style adds `:name`
     * @param SearchPath|null $searchPath The schemas an unqualified relation name is searched in, or null for the default of the database
     *
     * @throws InvalidArgumentException When the release, mode or search path does not belong to the database
     */
    public function __construct(?string $grammarVersion = null, ?Mode $mode = null, ParameterStyle $parameters = ParameterStyle::Native, ?SearchPath $searchPath = null)
    {
        $platform = Platform::shared();
        $this->profile = $platform->profile($grammarVersion, $mode, $parameters);
        $this->parser = $platform->parser($this->profile);
        $platform->context($this->profile, $searchPath?->schemas, [], false);
        $this->catalog = new Catalog($this->profile, $searchPath?->schemas, $this->parser);
        $this->binder = new StatementBinder($this->parser);
    }

    /**
     * Answers the fixed language profile.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * Builds a context from explicit SQL relation definitions.
     *
     * Null describes an open catalog. An explicit list can enumerate the entire catalog.
     * @param list<string>|null $declarations Relation definition statements in dependency order
     */
    public function context(?array $declarations = null, bool $complete = true): Context
    {
        return $this->catalog->build($declarations, $complete);
    }

    /**
     * Resolves a statement and returns its complete database-specific representation.
     *
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws SemanticException When the analysis establishes a semantic error
     * @throws InvalidConstruction When the statement cannot be represented in the supported complete IR
     */
    public function analyze(string $sql, ?Context $context = null): Select
    {
        $declarations = $this->catalog->resolve($context);
        return (new SelectLowering())->lower($this->binder->resolve($sql, $declarations));
    }

    /**
     * Publishes a complete value for each statement of a script against the same explicit declaration context.
     *
     * No statement is executed for the ones after it.
     *
     * @return list<Select>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     * @throws SemanticException When a statement has a semantic problem
     * @throws InvalidConstruction When any statement has no complete representation
     */
    public function analyzeAll(string $sql, ?Context $context = null): array
    {
        $declarations = $this->catalog->resolve($context);
        $results = [];
        foreach ($this->split($sql) as $statement) {
            $results[] = $this->analyze($statement, $declarations);
        }

        return $results;
    }

    /**
     * Finds the statement boundaries of a script, as the database finds them.
     *
     * Each text ends with its own terminator; trailing whitespace and comments
     * stay with the last statement. A semicolon inside a string, a comment, or
     * a compound statement ends nothing.
     *
     * @return list<string>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     */
    public function split(string $sql): array
    {
        try {
            return (new Splitter($this->parser))->split($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }
}
