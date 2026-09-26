<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Statement\Statement;

/**
 * Analyzes the complete language independently of schema-dependent binding.
 *
 * @visibility SqlSemantics
 */
final class Analyzer
{
    private readonly DialectParser $parser;
    private readonly ValueReader $values;

    /**
     * Selects the language and its corresponding complete value model.
     */
    public function __construct(Dialect $dialect, ?string $grammarVersion = null)
    {
        $this->parser = new DialectParser($dialect, $grammarVersion);
        $this->values = $dialect->platform()->values($this->parser->version());
    }

    /**
     * Parses and lowers SQL, retaining only the independent statement data and its comments.
     *
     * @throws AnalysisException When SQL is not in the selected language
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
}
