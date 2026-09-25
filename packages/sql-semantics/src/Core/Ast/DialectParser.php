<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Dialect;

/**
 * Selects and reuses the syntax parser for the semantic phase's language context.
 *
 * @visibility SqlSemantics
 */
final class DialectParser
{
    private readonly SqlParser $parser;

    /**
     * @param string|null $version A release tag shipped by sql-parser
     */
    public function __construct(Dialect $dialect, ?string $version = null)
    {
        $this->parser = $dialect->platform()->parser($version);
    }

    /**
     * Parses SQL without discarding source trivia or locations.
     *
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the grammar
     */
    public function parse(string $sql): Node
    {
        return $this->parser->parse($sql);
    }

    /**
     * Returns the resolved grammar release so binding can reuse the declaration language.
     */
    public function version(): string
    {
        return $this->parser->version();
    }
}
