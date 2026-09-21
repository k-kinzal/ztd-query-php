<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql;

use RuntimeException;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\TerminalIndex;
use SqlParser\Lexer\Token;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\Lexer\PostgreSqlLexer;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableFile;

/**
 * Parses PostgreSQL statements with the grammar of a chosen server release.
 *
 * The parse table is built from the `gram.y` of that release and the lexer
 * follows its `scan.l`, so the tree names the nonterminals of the official
 * grammar. Several statements separated by semicolons parse as one tree.
 *
 * @visibility public
 *
 * @example Parsing with the default release
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $tree = $parser->parse('SELECT id FROM users WHERE id = $1');
 *     $tree->name // => 'parse_toplevel'
 *     count($tree->find('relation_expr')) // => 1
 * @example Writing a parsed statement back
 *     $sql = "SELECT id FROM users -- everyone\n";
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $parser->parse($sql)->toString() === $sql // => true
 * @example Rejecting an unsupported release
 *     new \SqlParser\PostgreSql\PostgreSqlParser('pg-9.6.0') // throws \RuntimeException: Unsupported
 */
final class PostgreSqlParser
{
    private readonly PostgreSqlVersion $version;

    private readonly ParseTable $table;

    private readonly PostgreSqlLexer $lexer;

    /**
     * Loads the parser of one release.
     *
     * @param string|null $version Release tag such as `pg-17.2`, or null for the newest shipped
     * @param VersionRegistry $registry Record of shipped releases
     *
     * @throws RuntimeException When the release is not shipped or its resources are missing
     */
    public function __construct(?string $version = null, VersionRegistry $registry = new VersionRegistry())
    {
        $this->version = PostgreSqlVersion::resolve($version, $registry);
        $this->table = (new TableFile())->load($this->version->release->tablePath);
        $this->lexer = new PostgreSqlLexer(KeywordTable::load($this->version->release->keywordPath));
    }

    /**
     * Answers the release the parser reads for.
     *
     * @return string Release tag such as `pg-17.2`
     */
    public function version(): string
    {
        return $this->version->name();
    }

    /**
     * Reads SQL text into the terminals of the grammar, the end marker last.
     *
     * A token carries the whitespace and comments skipped before it and the
     * end marker what follows the last of them, so the tokens hold every byte
     * of the text.
     *
     * @param string $sql The SQL text
     *
     * @return list<Token> The tokens in text order
     *
     * @throws LexicalException When the text holds something no token starts with
     */
    public function tokenize(string $sql): array
    {
        return (new TerminalIndex($this->table->symbols))->tokens($this->lexer->scan($sql), $sql);
    }

    /**
     * Parses one or more statements.
     *
     * @param string $sql The SQL text
     *
     * @return Node The tree, rooted at the grammar's start symbol, holding every byte of the text
     *
     * @throws LexicalException When the text holds something no token starts with
     * @throws SyntaxException When the text is not in the grammar of the release
     */
    public function parse(string $sql): Node
    {
        return (new LrParser($this->table))->parse($this->tokenize($sql), $sql);
    }

    /**
     * Answers every release tag the package ships a PostgreSQL grammar for, oldest first.
     *
     * @return list<string> Release tags
     */
    public static function versions(): array
    {
        return (new VersionRegistry())->names(PostgreSqlVersion::DIALECT);
    }
}
