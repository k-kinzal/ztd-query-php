<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Dialect;

/**
 * Selects and reuses the syntax parser for the semantic phase's language context.
 *
 * @visibility SqlSemantics
 */
final class DialectParser
{
    private readonly MySqlParser|PostgreSqlParser|SqliteParser $parser;

    /**
     * @param string|null $version A release tag shipped by sql-parser
     */
    public function __construct(Dialect $dialect, ?string $version = null)
    {
        $this->parser = match ($dialect) {
            Dialect::PostgreSql => new PostgreSqlParser($version),
            Dialect::MySql => new MySqlParser($version),
            Dialect::Sqlite => new SqliteParser($version),
        };
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
