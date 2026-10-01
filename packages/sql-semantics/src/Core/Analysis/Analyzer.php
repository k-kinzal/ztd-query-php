<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Resolution;
use SqlSemantics\Statement\Statement;

/**
 * Analyzes the complete language, resolving names against dependencies when they are given.
 *
 * @visibility SqlSemantics
 */
final class Analyzer
{
    private readonly DialectParser $parser;
    private readonly ValueReader $values;
    private readonly Resolver $resolver;

    /**
     * Reads SQL of the language, resolving unqualified table names in the schemas of the search path.
     *
     * @param non-empty-list<string> $path The schemas an unqualified name is read in, in order
     */
    public function __construct(private readonly Language $language, array $path)
    {
        $this->parser = new DialectParser($language);
        $this->values = $language->values();
        $this->resolver = new Resolver($language, new SchemaReader(new Identifiers($language->dialect), $path[0], $this->values, $language), $path);
    }

    /**
     * Parses and lowers one statement, resolving its table names against the dependencies when they are given.
     *
     * Dependencies provide declarations, not a history to execute. Reading
     * a dependency never applies another dependency's DDL effects.
     *
     * @param list<Statement>|null $dependencies The declaration context, or null to structure it only
     * @param Declarations $declarations Whether the dependencies declare every table the statement names
     *
     * @throws AnalysisException When SQL is not one statement of the selected language
     * @throws \SqlSemantics\Core\SemanticException When a name resolves to nothing under complete declarations or a declaration conflicts
     */
    public function analyze(string $sql, ?array $dependencies = null, Declarations $declarations = Declarations::Complete): Statement
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $statement = $this->values->statement($tree);
        if ($dependencies === null) {
            return $statement;
        }
        $resolved = [];
        foreach ($dependencies as $dependency) {
            $resolution = $dependency->resolution ?? $this->analyze($dependency->toString(), [], Declarations::Partial)->resolution ?? new Resolution();
            $resolved[] = [$dependency, $resolution];
        }

        return new Statement($statement->command, $statement->comments, $this->resolver->resolve($tree, $statement->command, $resolved, $declarations));
    }

    /**
     * Parses and lowers every statement of a script against the same explicit context.
     *
     * @param list<Statement>|null $dependencies
     * @return list<Statement>
     * @throws AnalysisException When a statement is not in the selected language
     * @throws \SqlSemantics\Core\SemanticException When a name resolves to nothing under complete declarations or a declaration conflicts
     */
    public function analyzeAll(string $sql, ?array $dependencies = null, Declarations $declarations = Declarations::Complete): array
    {
        $statements = [];
        foreach ($this->split($sql) as $text) {
            $statements[] = $this->analyze($text, $dependencies, $declarations);
        }

        return $statements;
    }

    /**
     * Finds the statement boundaries of a script.
     *
     * @return list<string>
     * @throws AnalysisException When a statement is not in the selected language
     */
    public function split(string $sql): array
    {
        try {
            return $this->parser->split($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }
}
