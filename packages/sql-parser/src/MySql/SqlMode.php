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
     * Answers the mode a fresh MySQL server runs with.
     *
     * @return self The default mode
     */
    public static function default(): self
    {
        return new self();
    }
}
