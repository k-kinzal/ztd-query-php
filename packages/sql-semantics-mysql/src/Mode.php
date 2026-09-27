<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\MySql\SqlMode;

/**
 * The MySQL session `sql_mode`, as far as it changes how SQL text is read and written.
 *
 * `ANSI_QUOTES` makes double quotes delimit identifiers, `NO_BACKSLASH_ESCAPES`
 * makes a backslash an ordinary character in a string, `PIPES_AS_CONCAT` makes
 * `||` concatenation, `HIGH_NOT_PRECEDENCE` makes `NOT` bind as tightly as `!`,
 * and `IGNORE_SPACE` lets a space separate a function name from its
 * parenthesis. Pass the value a session reports with `SELECT @@SESSION.sql_mode`.
 *
 * @visibility public
 * @example Reading a session's mode
 *     $mode = \SqlSemantics\Platform\MySql\Mode::fromString('ANSI_QUOTES,STRICT_TRANS_TABLES');
 *     $mode->toString() // => 'ANSI_QUOTES'
 * @example Analyzing under the mode
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, mode: \SqlSemantics\Platform\MySql\Mode::fromString('ANSI_QUOTES'));
 *     $semantics->analyze('SELECT "x" FROM t')->toString() // => 'SELECT "x" FROM t'
 */
final class Mode implements \SqlSemantics\Core\Mode
{
    /**
     * Supplies the tokenization flags of the mode.
     */
    public function __construct(public readonly SqlMode $sqlMode = new SqlMode())
    {
    }

    /**
     * Reads a `sql_mode` value as the server reports it, including combination modes such as `ANSI`.
     */
    public static function fromString(string $sqlMode): self
    {
        return new self(SqlMode::fromString($sqlMode));
    }

    /**
     * Spells the flags that are on as `sql_mode` names, comma separated.
     */
    public function toString(): string
    {
        $names = [];
        foreach (['ANSI_QUOTES' => $this->sqlMode->ansiQuotes, 'PIPES_AS_CONCAT' => $this->sqlMode->pipesAsConcat, 'HIGH_NOT_PRECEDENCE' => $this->sqlMode->highNotPrecedence, 'NO_BACKSLASH_ESCAPES' => $this->sqlMode->noBackslashEscapes, 'IGNORE_SPACE' => $this->sqlMode->ignoreSpace] as $name => $on) {
            if ($on) {
                $names[] = $name;
            }
        }

        return implode(',', $names);
    }
}
