<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

/**
 * A stored procedure or stored function: its signature, characteristics and body as the server stores them.
 *
 * The parameter list and the body are kept as they were written; the characteristics are kept
 * as values, which SHOW CREATE writes in a fixed order. Routine names are not case-sensitive.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility MySqlMemory
 */
final class Routine
{
    /**
     * @param string $schema The database of the routine
     * @param string $name The name as it was created
     * @param array{string, string} $definer The user and host of the definer
     * @param string $parameters The text of the parameter list, between its parentheses
     * @param string $returns The type a function returns, as SHOW CREATE writes it; empty for a procedure
     * @param string $body The text of the body
     * @param string $access The data access characteristic: CONTAINS SQL, NO SQL, READS SQL DATA or MODIFIES SQL DATA
     * @param bool $deterministic Whether the routine is declared DETERMINISTIC
     * @param string $security The security context: DEFINER or INVOKER
     * @param string $comment The comment
     * @param string $mode The sql_mode the routine was created under
     * @param string $created When the routine was created, as `YYYY-MM-DD hh:mm:ss`
     * @param string $modified When the routine was last changed, as `YYYY-MM-DD hh:mm:ss`
     * @param array{string, string, string} $charsets The character_set_client, collation_connection and database collation the routine was created with
     * @param CreateProcedure|CreateFunction $statement The statement that created the routine
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $definer,
        public readonly string $parameters,
        public readonly string $returns,
        public readonly string $body,
        public string $access,
        public readonly bool $deterministic,
        public string $security,
        public string $comment,
        public readonly string $mode,
        public readonly string $created,
        public string $modified,
        public readonly array $charsets,
        public readonly CreateProcedure|CreateFunction $statement,
    ) {
    }

    /**
     * Answers PROCEDURE or FUNCTION.
     */
    public function kind(): string
    {
        return $this->statement instanceof CreateFunction ? 'FUNCTION' : 'PROCEDURE';
    }

    /**
     * Answers the statement SHOW CREATE writes for the routine.
     *
     * The characteristics that differ from the defaults are written each on a line of their own,
     * the data access first, then DETERMINISTIC, SQL SECURITY INVOKER and the comment (verified
     * on a live 8.4 server).
     */
    public function create(): string
    {
        $text = 'CREATE DEFINER=' . self::quoted($this->definer[0]) . '@' . self::quoted($this->definer[1]) . ' ' . $this->kind() . ' ' . self::quoted($this->name) . '(' . $this->parameters . ')';
        if ($this->returns !== '') {
            $text .= ' RETURNS ' . $this->returns;
        }
        $text .= "\n";
        if ($this->access !== 'CONTAINS SQL') {
            $text .= '    ' . $this->access . "\n";
        }
        if ($this->deterministic) {
            $text .= "    DETERMINISTIC\n";
        }
        if ($this->security === 'INVOKER') {
            $text .= "    SQL SECURITY INVOKER\n";
        }
        if ($this->comment !== '') {
            $text .= '    COMMENT ' . self::literal($this->comment) . "\n";
        }

        return $text . $this->body;
    }

    /**
     * Writes a string literal as SHOW CREATE writes a comment: a quote doubled, a backslash and the control characters escaped.
     */
    public static function literal(string $text): string
    {
        return "'" . strtr($text, ['\\' => '\\\\', "'" => "''", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1A" => '\\Z']) . "'";
    }

    /**
     * Quotes an identifier with backticks, doubling the backticks it holds.
     */
    public static function quoted(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
