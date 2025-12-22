<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\SqlDialect;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;

/**
 * MySQL dialect implementation backed by phpMyAdmin SQL parser.
 */
final class MySqlDialect implements SqlDialect
{
    /**
     * Create a Parser instance with PHP 8.5+ compatibility.
     *
     * Suppresses warnings for large number to int conversion that occur
     * in phpmyadmin/sql-parser when parsing SQL with very large numeric literals.
     */
    public static function createParser(string $sql): Parser
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'is not representable as an int')) {
                return true;
            }
            return false;
        });

        try {
            return new Parser($sql);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Parse SQL into statements.
     *
     * @return array<int, Statement>
     */
    public function parse(string $sql): array
    {
        $parser = self::createParser($sql);
        return array_values($parser->statements);
    }

    /**
     * Emit SQL from a parsed statement.
     */
    public function emit(Statement $statement): string
    {
        return $statement->build();
    }
}
