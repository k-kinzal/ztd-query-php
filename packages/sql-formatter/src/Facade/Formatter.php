<?php

declare(strict_types=1);

namespace SqlFormatter\Facade;

/**
 * Formats SQL with the dialect, release, and lexical settings of a supplied parser.
 *
 * Compact canonicalizes optional syntax and keywords; other styles preserve tokens.
 * Output is parsed again to verify the selected style's preservation contract.
 *
 * @visibility public
 * @example Formatting a query
 *     $formatter = new \SqlFormatter\Facade\Formatter(new \SqlParser\Sqlite\SqliteParser());
 *     $formatter->format('SELECT id,name FROM users') // => "SELECT\n    id,\n    name\nFROM\n    users"
 * @example Selecting compact output
 *     $formatter = new \SqlFormatter\Facade\Formatter(new \SqlParser\MySql\MySqlParser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
 *     $formatter->format("SELECT  id, name\nFROM users") // => 'SELECT id,name FROM users'
 */
final class Formatter
{
    private readonly \SqlFormatter\Core\Formatter $formatter;

    /**
     * Selects built-in formatting rules or accepts caller-supplied rules.
     */
    public function __construct(
        \SqlParser\Parser\SqlParser $parser,
        \SqlFormatter\Core\FormatOptions $options = new \SqlFormatter\Core\FormatOptions(),
        ?\SqlFormatter\Core\Dialect $dialect = null,
    ) {
        $this->formatter = new \SqlFormatter\Core\Formatter($parser, $dialect ?? DialectFactory::forParser($parser), $options);
    }

    /**
     * Formats SQL using the configured parser, grammar rules, and layout.
     *
     * @throws \SqlParser\Lexer\SourceException When the input is not accepted
     * @throws \SqlFormatter\Core\FormattingException When output verification fails
     */
    public function format(string $sql): string
    {
        return $this->formatter->format($sql);
    }
}
