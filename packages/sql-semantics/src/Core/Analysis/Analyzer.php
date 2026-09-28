<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Resolution;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\StatementException;

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
        $this->resolver = new Resolver($language, new SchemaReader(new Identifiers($language->dialect), $path[0], $this->values), $path);
    }

    /**
     * Parses and lowers one statement, resolving its table names against the dependencies when they are given.
     *
     * @param list<Statement>|null $dependencies The declarations the statement is read against, in order, or null to structure it only
     * @param Declarations $declarations Whether the dependencies declare every table the statement names
     *
     * @throws AnalysisException When SQL is not one statement of the selected language
     * @throws \SqlSemantics\Core\SemanticException When a name resolves to nothing under complete declarations or a declaration conflicts
     * @throws LogicException When the model of the release cannot write what it read, a defect of the models
     */
    public function analyze(string $sql, ?array $dependencies = null, Declarations $declarations = Declarations::Complete): Statement
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        [$command, $comments] = $this->values->command($tree);
        $resolution = $dependencies === null ? null : $this->resolve($tree, $command, $dependencies, $declarations);
        try {
            return new Statement($this->language, $command, $comments, $resolution);
        } catch (StatementException $error) {
            throw new LogicException('The model of ' . $this->language->version . ' does not write back what it read: ' . $error->getMessage(), 0, $error);
        }
    }

    /**
     * Resolves the table names of a command, read from its tree, against the dependencies.
     *
     * A dependency analyzed without dependencies is resolved from a tree of
     * its own SQL against the dependencies before it; the nodes of that tree
     * answer the values of the dependency's command, so what it declares is
     * made of the values it holds. A reference to a table a dependency
     * declares names the dependency given.
     *
     * @param list<Statement> $dependencies The declarations the command is read against, in order
     *
     * @throws \SqlSemantics\Core\SemanticException When a name resolves to nothing under complete declarations or a declaration conflicts
     * @throws LogicException When a dependency is not SQL this language reads as itself
     */
    public function resolve(Node $tree, Command $command, array $dependencies, Declarations $declarations = Declarations::Complete): Resolution
    {
        $resolved = [];
        $before = [];
        foreach ($dependencies as $dependency) {
            $resolution = $dependency->resolution;
            if ($resolution === null) {
                try {
                    $own = $this->parser->parse($dependency->toString());
                } catch (SourceException $error) {
                    throw new LogicException('A dependency is not SQL of ' . $this->language->version . ': ' . $error->getMessage(), 0, $error);
                }
                $this->values->adopt($own, $dependency->command);
                $resolution = $this->resolve($own, $dependency->command, $before, $declarations);
            }
            $resolved[] = [$dependency, $resolution];
            $before[] = $dependency->resolution === null ? new Statement($dependency->syntax, $dependency->command, $dependency->comments, $resolution) : $dependency;
        }

        return $this->resolver->resolve($tree, $command, $resolved, $declarations);
    }

    /**
     * Parses and lowers every statement of a script, each resolved against the dependencies and the statements before it.
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
            $statements[] = $this->analyze($text, $dependencies === null ? null : [...$dependencies, ...$statements], $declarations);
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
