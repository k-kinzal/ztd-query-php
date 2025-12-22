<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;

/**
 * MySQL parser implementation backed by phpMyAdmin SQL parser.
 */
final class MySqlParser
{
    /**
     * Parse SQL into an array of statements with PHP 8.5+ compatibility.
     *
     * Suppresses warnings for large number to int conversion that occur
     * in phpmyadmin/sql-parser when parsing SQL with very large numeric literals.
     *
     * @return array<int, Statement>
     */
    public function parse(string $sql): array
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'is not representable as an int')) {
                return true;
            }
            return false;
        });

        try {
            $parser = new Parser($sql);
            return array_values($parser->statements);
        } finally {
            restore_error_handler();
        }
    }
}
