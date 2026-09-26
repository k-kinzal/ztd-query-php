<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Statement;

/**
 * Analyzes the complete language independently of schema state.
 *
 * @visibility SqlSemantics
 */
final class Analyzer
{
    private readonly DialectParser $parser;
    private readonly ValueReader $values;

    /**
     * Reads SQL of the language.
     */
    public function __construct(Language $language)
    {
        $this->parser = new DialectParser($language);
        $this->values = $language->values();
    }

    /**
     * Parses and lowers one statement, retaining only the independent statement data and its comments.
     *
     * @throws AnalysisException When SQL is not one statement of the selected language
     */
    public function analyze(string $sql): Statement
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }

        return $this->values->statement($tree);
    }

    /**
     * Parses and lowers every statement of a script, in order.
     *
     * @return list<Statement>
     * @throws AnalysisException When a statement is not in the selected language
     */
    public function analyzeAll(string $sql): array
    {
        return array_map(fn (string $statement): Statement => $this->analyze($statement), $this->split($sql));
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
