<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Schema;

/**
 * Resolves declaration SQL into immutable state used by statement binding.
 * @visibility SqlSemantics
 */
final class SchemaAnalyzer
{
    private readonly DialectParser $parser;
    private readonly string $defaultSchema;

    /**
     * Selects a language, release and default declaration namespace.
     */
    public function __construct(private readonly Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null)
    {
        $this->defaultSchema = $defaultSchema ?? $dialect->platform()->defaultSchema();
        $this->parser = new DialectParser($dialect, $grammarVersion);
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
     * Compatibility path preserving the original parser exception contract.
     * @throws \SqlSemantics\Core\SemanticException When state facts conflict or cannot be resolved
     * @throws SourceException When declarations are outside the selected language
     */
    public function read(string ...$sql): Schema
    {
        $trees = [];
        foreach ($sql as $text) {
            array_push($trees, ...$this->parser->parseScript($text));
        }
        $values = $this->dialect->platform()->values($this->parser->version());
        $tables = (new SchemaReader(new Identifiers($this->dialect), $this->defaultSchema, $values))->read($trees);

        return new Schema($this->dialect, $tables, $this->defaultSchema, $this->parser->version());
    }
}
