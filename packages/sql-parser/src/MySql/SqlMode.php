<?php

declare(strict_types=1);

namespace SqlParser\MySql;

/**
 * The parts of MySQL's `sql_mode` that change how text is tokenized.
 *
 * @visibility public
 *
 * @example Reading double quotes as identifiers
 *     $mode = new \SqlParser\MySql\SqlMode(ansiQuotes: true);
 *     $parser = new \SqlParser\MySql\MySqlParser(mode: $mode);
 *     $parser->tokenize('SELECT "x"')[1]->name // => 'IDENT_QUOTED'
 * @example Reading the mode a session reports
 *     $mode = \SqlParser\MySql\SqlMode::fromString('ANSI,NO_BACKSLASH_ESCAPES,STRICT_TRANS_TABLES');
 *     [$mode->ansiQuotes, $mode->pipesAsConcat, $mode->ignoreSpace, $mode->noBackslashEscapes, $mode->highNotPrecedence] // => [true, true, true, true, false]
 */
final class SqlMode
{
    /**
     * @param bool $ansiQuotes Whether double quotes delimit identifiers instead of strings
     * @param bool $pipesAsConcat Whether `||` is string concatenation instead of `OR`
     * @param bool $highNotPrecedence Whether `NOT` binds as tightly as `!`
     * @param bool $noBackslashEscapes Whether a backslash in a string is an ordinary character
     * @param bool $ignoreSpace Whether a function name may be followed by spaces before its parenthesis
     */
    public function __construct(
        public readonly bool $ansiQuotes = false,
        public readonly bool $pipesAsConcat = false,
        public readonly bool $highNotPrecedence = false,
        public readonly bool $noBackslashEscapes = false,
        public readonly bool $ignoreSpace = false,
    ) {
    }

    /**
     * The combination modes and the modes each of them includes, by release history.
     *
     * @var array<string, list<string>>|null
     */
    private static ?array $combinations = null;

    /**
     * Answers the mode a fresh MySQL server runs with.
     *
     * @return self The default mode
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Reads a `sql_mode` value as the server reports it.
     *
     * The value is a comma-separated list of mode names, in any letter case,
     * such as `SELECT @@SESSION.sql_mode` answers. A combination mode, such as
     * `ANSI` or one of the compatibility modes releases before 8.0 had, turns
     * on the modes it includes, as `resources/sql-modes.php` records them.
     * Names that do not change tokenization, such as `STRICT_TRANS_TABLES`,
     * are read and have no effect.
     *
     * @param string $modes The `sql_mode` value
     *
     * @return self The flags the value turns on
     */
    public static function fromString(string $modes): self
    {
        $combinations = self::$combinations;
        if ($combinations === null) {
            $combinations = [];
            foreach ((array) require dirname(__DIR__, 2) . '/resources/sql-modes.php' as $name => $included) {
                $combinations[(string) $name] = array_values(array_filter((array) $included, 'is_string'));
            }
            self::$combinations = $combinations;
        }
        $flags = [];
        foreach (explode(',', \SqlParser\Lexer\Ascii::upper($modes)) as $name) {
            $name = trim($name);
            foreach ($combinations[$name] ?? [$name] as $flag) {
                $flags[$flag] = true;
            }
        }

        return new self(
            ansiQuotes: isset($flags['ANSI_QUOTES']),
            pipesAsConcat: isset($flags['PIPES_AS_CONCAT']),
            highNotPrecedence: isset($flags['HIGH_NOT_PRECEDENCE']),
            noBackslashEscapes: isset($flags['NO_BACKSLASH_ESCAPES']),
            ignoreSpace: isset($flags['IGNORE_SPACE']),
        );
    }
}
