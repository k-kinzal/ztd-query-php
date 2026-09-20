<?php

declare(strict_types=1);

namespace SqlParser\MySql;

use RuntimeException;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\TerminalIndex;
use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\MySqlLexer;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\Printer;
use SqlParser\Parser\RenderException;
use SqlParser\Parser\SyntaxException;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableFile;

/**
 * Parses MySQL statements with the grammar of a chosen server release.
 *
 * The parse table is built from the `sql_yacc.yy` of that release and the
 * lexer follows its `sql_lex.cc`, so the tree names the nonterminals of the
 * official grammar. One statement is parsed at a time, as the server does.
 *
 * @visibility public
 *
 * @example Parsing with the default release
 *     $parser = new \SqlParser\MySql\MySqlParser();
 *     $tree = $parser->parse('SELECT id FROM users WHERE id = ?');
 *     $tree->name // => 'start_entry'
 *     count($tree->find('table_reference')) // => 1
 * @example Choosing a release
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-5.7.44');
 *     $parser->version() // => 'mysql-5.7.44'
 * @example Rejecting an unsupported release
 *     new \SqlParser\MySql\MySqlParser('mysql-4.1.0') // throws \RuntimeException: Unsupported
 */
final class MySqlParser implements Tokenizer
{
    private readonly MySqlVersion $version;

    private readonly ParseTable $table;

    private readonly MySqlLexer $lexer;

    /**
     * Loads the parser of one release.
     *
     * @param string|null $version Release tag such as `mysql-8.4.7`, or null for the newest shipped
     * @param SqlMode $mode The `sql_mode` flags that change tokenization
     * @param VersionRegistry $registry Record of shipped releases
     *
     * @throws RuntimeException When the release is not shipped or its resources are missing
     */
    public function __construct(?string $version = null, SqlMode $mode = new SqlMode(), VersionRegistry $registry = new VersionRegistry())
    {
        $this->version = MySqlVersion::resolve($version, $registry);
        $this->table = (new TableFile())->load($this->version->release->tablePath);
        $this->lexer = new MySqlLexer(KeywordTable::load($this->version->release->keywordPath), $this->version, $mode);
    }

    /**
     * Answers the release the parser reads for.
     *
     * @return string Release tag such as `mysql-8.4.7`
     */
    public function version(): string
    {
        return $this->version->name();
    }

    /**
     * Reads SQL text into the terminals of the grammar, the end marker last.
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
     * Parses one statement.
     *
     * @param string $sql The SQL text
     *
     * @return Node The tree, rooted at the grammar's start symbol
     *
     * @throws LexicalException When the text holds something no token starts with
     * @throws SyntaxException When the statement is not in the grammar of the release
     */
    public function parse(string $sql): Node
    {
        return (new LrParser($this->table))->parse($this->tokenize($sql), $sql);
    }


    /**
     * Writes a tree back out as MySQL text.
     *
     * A tree a rewrite has changed no longer stands for the text it was
     * parsed from, so its SQL is written from its tokens, and written so
     * that this parser reads it back as the tree holds it.
     *
     * @param Node|Token $tree The tree, or one token of it
     *
     * @return string The SQL text
     *
     * @throws RenderException When two tokens of the tree cannot be written next to each other
     *
     * @visibility public
     *
     * @example Writing a parsed statement back out
     *     $parser = new \SqlParser\MySql\MySqlParser();
     *     $parser->render($parser->parse('SELECT  COUNT(*)  FROM users')) // => 'SELECT COUNT(*) FROM users'
     */
    public function render(Node|Token $tree): string
    {
        return Printer::of($this)->render($tree);
    }

    /**
     * Answers every release tag the package ships a MySQL grammar for, oldest first.
     *
     * @return list<string> Release tags
     */
    public static function versions(): array
    {
        return (new VersionRegistry())->names(MySqlVersion::DIALECT);
    }
}
