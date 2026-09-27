<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Schema;

/**
 * Resolves declaration SQL into the immutable state statements are analyzed against.
 * @visibility SqlSemantics
 */
final class SchemaAnalyzer
{
    private readonly DialectParser $parser;
    private readonly string $defaultSchema;

    /**
     * Reads declarations of the language into the default declaration namespace.
     */
    public function __construct(private readonly Language $language, ?string $defaultSchema = null)
    {
        $this->defaultSchema = $defaultSchema ?? $language->dialect->platform()->defaultSchema();
        $this->parser = new DialectParser($language);
    }

    /**
     * Builds a closed table state without retaining parser nodes or SQL text.
     * @throws \SqlSemantics\Core\SemanticException When state facts conflict or cannot be resolved
     * @throws AnalysisException When declarations are outside the selected language
     */
    public function analyze(string ...$sql): Schema
    {
        try {
            return $this->read(...$sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }

    /**
     * Reads declarations, reporting syntax errors with the parser's own exceptions.
     * @throws \SqlSemantics\Core\SemanticException When state facts conflict or cannot be resolved
     * @throws SourceException When declarations are outside the selected language
     */
    public function read(string ...$sql): Schema
    {
        $trees = [];
        foreach ($sql as $text) {
            array_push($trees, ...$this->parser->parseScript($text));
        }
        $tables = (new SchemaReader(new Identifiers($this->language->dialect), $this->defaultSchema, $this->language->values()))->read($trees);

        return new Schema($this->language->dialect, $tables, $this->defaultSchema, $this->language->version);
    }
}
