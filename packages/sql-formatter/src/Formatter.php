<?php

declare(strict_types=1);

namespace SqlFormatter;

use SqlFormatter\Layout\Renderer;
use SqlFormatter\Syntax\Document;
use SqlFormatter\Syntax\Fingerprint;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

/**
 * Formats SQL with the dialect, release, and lexical settings of a supplied parser.
 *
 * Tokens, identifiers, literals, comments, and explicit parentheses are preserved.
 * Output is parsed again to verify that formatting preserves the grammar derivation.
 *
 * @visibility public
 * @example Formatting a query
 *     $formatter = new \SqlFormatter\Formatter(new \SqlParser\Sqlite\SqliteParser());
 *     $formatter->format('SELECT id,name FROM users') // => "SELECT\n    id,\n    name\nFROM\n    users"
 * @example Selecting compact output
 *     $formatter = new \SqlFormatter\Formatter(new \SqlParser\MySql\MySqlParser(), new \SqlFormatter\FormatOptions(\SqlFormatter\Style::Compact));
 *     $formatter->format("SELECT  id, name\nFROM users") // => 'SELECT id, name FROM users'
 */
final class Formatter
{
    /**
     * Reuses a configured parser and an immutable layout preset across calls.
     */
    public function __construct(
        private readonly MySqlParser|PostgreSqlParser|SqliteParser $parser,
        private readonly FormatOptions $options = new FormatOptions(),
    ) {
    }

    /**
     * @throws SourceException When the input is not accepted by the selected grammar
     * @throws FormattingException When output verification detects changed syntax
     */
    public function format(string $sql): string
    {
        $tree = $this->parser->parse($sql);
        $result = (new Renderer(Document::from($tree), $this->options))->render();
        try {
            $formatted = $this->parser->parse($result);
        } catch (SourceException $exception) {
            throw new FormattingException('Formatted SQL could not be parsed with the original grammar.', 0, $exception);
        }
        if (Fingerprint::of($tree) !== Fingerprint::of($formatted)) {
            throw new FormattingException('Formatting changed the SQL syntax tree.');
        }
        return $result;
    }
}
