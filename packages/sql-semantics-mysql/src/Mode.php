<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\MySql\SqlMode;

/**
 * The parts of a MySQL session's `sql_mode` that change how SQL text is read.
 *
 * Five modes change tokenization: `ANSI_QUOTES`, `PIPES_AS_CONCAT`,
 * `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES` and `IGNORE_SPACE`. A
 * combination mode such as `ANSI` turns on the ones it includes. Every other
 * mode name, such as `STRICT_TRANS_TABLES` or `REAL_AS_FLOAT`, is read and not
 * recorded: it changes no token, and a fact that depends on it names the
 * session state it is missing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html.
 *
 * @visibility public
 * @example Reading the mode a session reports
 *     \SqlSemantics\Platform\MySql\Mode::fromString('STRICT_TRANS_TABLES,ansi_quotes,NO_BACKSLASH_ESCAPES')->toString() // => 'ANSI_QUOTES,NO_BACKSLASH_ESCAPES'
 * @example Reading double quotes as identifiers
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, null, \SqlSemantics\Platform\MySql\Mode::fromString('ANSI_QUOTES'));
 *     $semantics->analyze('SELECT "order" FROM t')->toString() // => 'SELECT `order` FROM t'
 */
final class Mode implements \SqlSemantics\Contract\Mode
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
     * Reads a `sql_mode` value as `SELECT @@SESSION.sql_mode` answers it.
     */
    public static function fromString(string $modes): self
    {
        $flags = SqlMode::fromString($modes);

        return new self($flags->ansiQuotes, $flags->pipesAsConcat, $flags->highNotPrecedence, $flags->noBackslashEscapes, $flags->ignoreSpace);
    }

    /**
     * Answers the recorded modes in the canonical spelling the language profile keeps.
     */
    public function toString(): string
    {
        $names = [];
        foreach (['ANSI_QUOTES' => $this->ansiQuotes, 'PIPES_AS_CONCAT' => $this->pipesAsConcat, 'HIGH_NOT_PRECEDENCE' => $this->highNotPrecedence, 'NO_BACKSLASH_ESCAPES' => $this->noBackslashEscapes, 'IGNORE_SPACE' => $this->ignoreSpace] as $name => $enabled) {
            if ($enabled) {
                $names[] = $name;
            }
        }

        return implode(',', $names);
    }
}
