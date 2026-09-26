<?php

declare(strict_types=1);

namespace SqlFormatter\Facade;

use InvalidArgumentException;
use SqlFormatter\Core\Dialect;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

/**
 * Composes built-in formatting rules with an already configured parser.
 *
 * @visibility SqlFormatter
 */
final class DialectFactory
{
    /**
     * Returns the matching formatting implementation without replacing the parser.
     *
     * @throws InvalidArgumentException When explicit rules are needed for this parser
     */
    public static function forParser(SqlParser $parser): Dialect
    {
        return match (true) {
            $parser instanceof MySqlParser => new \SqlFormatter\Platform\MySql\Dialect(),
            $parser instanceof PostgreSqlParser => new \SqlFormatter\Platform\PostgreSql\Dialect(),
            $parser instanceof SqliteParser => new \SqlFormatter\Platform\Sqlite\Dialect(),
            default => throw new InvalidArgumentException('Supply formatting rules for this parser.'),
        };
    }
}
