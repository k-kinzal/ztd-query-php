<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

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
     * Whether a MySQL 8.0+ dictionary lookup has loaded this definition into the shared cache.
     */
    public bool $metadataLoaded = false;

    /**
     * The type the function returns, once it is known.
     */
    private ?\MySqlMemory\Typing\Domain $returnType = null;

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
     * @param CreateProcedure|CreateFunction|null $statement The statement that created the routine, absent for installed metadata
     * @param Program\Installed|null $installed Public metadata of an installed routine, without an implementation body
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
        public readonly CreateProcedure|CreateFunction|null $statement,
        public readonly ?Program\Installed $installed = null,
    ) {
    }

    /**
     * Answers the type a function returns: the type of a column declared as its RETURNS clause, in the collation of the database of the function when the clause names none; a procedure returns NULL.
     */
    public function returned(): \MySqlMemory\Typing\Domain
    {
        if ($this->returnType !== null) {
            return $this->returnType;
        }
        if ($this->installed !== null) {
            return $this->returnType = $this->installed->returned();
        }
        $declared = new \MySqlMemory\Typing\Declared(Collation::named($this->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci'));
        $statement = $this->statement;
        if (!$statement instanceof CreateFunction) {
            return $this->returnType = \MySqlMemory\Typing\Domain::null();
        }
        $collation = $statement->collation?->name === null ? null : Collation::named($statement->collation->name->value);
        $domain = $declared->domain($statement->returns, $collation);
        if ($collation !== null && $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String && !$domain->collation->bytes()) {
            $domain = $domain->withCollation($collation, $domain->coercibility);
        }

        if ($domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String) {
            $domain = new \MySqlMemory\Typing\Domain($domain->kind, $domain->field, $domain->length, 0, $domain->unsigned, $domain->collation, true, $domain->members, $domain->coercibility);
        }

        return $this->returnType = $domain->withNullable(true);
    }

    /**
     * Answers PROCEDURE or FUNCTION.
     */
    public function kind(): string
    {
        return $this->installed?->metadata['ROUTINE_TYPE'] === 'FUNCTION' || $this->statement instanceof CreateFunction ? 'FUNCTION' : 'PROCEDURE';
    }

    /**
     * Answers the number of declared arguments, excluding the return value of a function.
     */
    public function parameterCount(): int
    {
        return $this->installed === null ? count($this->statement->parameters->parameters ?? []) : count(array_filter($this->installed->parameters, static fn (array $row): bool => $row['ORDINAL_POSITION'] !== 0));
    }

    /**
     * Answers the statement SHOW CREATE writes for the routine, or null when its body is not available.
     *
     * The characteristics that differ from the defaults are written each on a line of their own,
     * the data access first, then DETERMINISTIC, SQL SECURITY INVOKER and the comment (verified
     * on a live 8.4 server).
     */
    public function create(): ?string
    {
        if ($this->statement === null) {
            return null;
        }
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
