<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Finds the database or table a SHOW or DESCRIBE statement reports on, as the server opens it before it reads any clause.
 *
 * The database is the one written after FROM or IN, else the one written with the table name,
 * else the current one. A database that does not exist is ER_BAD_DB_ERROR and a table that does
 * not exist ER_NO_SUCH_TABLE, both before a column name of the WHERE clause is resolved. A
 * column pattern of DESCRIBE written as a hexadecimal or bit literal that is no utf8mb4 string
 * is warned about first, as the server reads it while it parses the statement (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html.
 *
 * @visibility MySqlMemory
 */
final class Inspection
{
    /**
     * Checks the database or table of a statement that reports on one, before the statement reads its clauses.
     *
     * @throws SqlError When the database or the table does not exist
     */
    public function check(Node $statement, Session $session): void
    {
        try {
            if ($statement instanceof ShowColumns || $statement instanceof ShowKeys) {
                $this->table($statement->table, $statement->database, $session);
            }
            if ($statement instanceof DescribeTable || $statement instanceof ShowCreateTable) {
                $this->table($statement->table, null, $session);
            }
            if ($statement instanceof ShowTableStatus) {
                $this->database($statement->database, $session);
            }
        } catch (SqlError $error) {
            $this->warn($statement, $session);

            throw $error;
        }
    }

    /**
     * Warns about a column pattern of DESCRIBE written as a hexadecimal or bit literal that is no utf8mb4 string.
     *
     * The command warns when the statement runs; when the table is missing, the check warns before it fails.
     */
    public function warn(Node $statement, Session $session): void
    {
        if (!$statement instanceof DescribeTable || !$statement->column instanceof Text || $statement->column->radix === null) {
            return;
        }
        $pattern = $this->pattern($statement->column);
        $utf8 = Charset::known('utf8mb4');
        if (!Encoding::valid($pattern, $utf8)) {
            $session->diagnostics->warning(ErrorCode::InvalidCharacterString, ErrorCode::InvalidCharacterString->message('utf8mb4', strtoupper(bin2hex(substr($pattern, Encoding::prefix($pattern, $utf8), 3)))));
        }
    }

    /**
     * Answers the bytes of a pattern written as a string, a hexadecimal or a bit literal.
     */
    public function pattern(Text $text): string
    {
        $digits = $text->value;

        return match ($text->radix) {
            Radix::Hexadecimal => (string) hex2bin(strlen($digits) % 2 === 1 ? '0' . $digits : $digits),
            Radix::Bit => implode('', array_map(static fn (string $octet): string => chr((int) bindec($octet)), $digits === '' ? [] : str_split(str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT), 8))),
            null => $digits,
        };
    }

    /**
     * Tells whether a statement reports a missing table itself, in its own order of checks.
     */
    public function inspects(Node $statement): bool
    {
        return $statement instanceof ShowColumns || $statement instanceof ShowKeys || $statement instanceof DescribeTable || $statement instanceof ShowCreateTable || $statement instanceof ShowTableStatus;
    }

    /**
     * Answers the database a statement names, else the current one.
     *
     * @throws SqlError When no database is named or current, or it does not exist
     */
    public function database(?Name $written, Session $session): Schema
    {
        $name = $written->value ?? $session->variables->database;
        if ($name === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($name);
        }

        return $schema;
    }

    /**
     * Answers the table a statement inspects.
     *
     * @param Name|null $database The database written after FROM or IN, which replaces the one written with the name
     *
     * @throws SqlError When the database or the table does not exist
     */
    public function table(InspectedTable $table, ?Name $database, Session $session): StoredTable
    {
        $schema = $this->database($database ?? $table->name->schema, $session);
        $name = $table->name->name->value;
        $stored = $schema->table($name);
        if ($stored === null && isset($schema->views[$name])) {
            return \MySqlMemory\Plan\Views::stored($schema->views[$name], $session->instance->dictionary);
        }
        if ($stored === null) {
            throw ErrorCode::NoSuchTable->error($schema->name, $name);
        }

        return $stored;
    }
}
