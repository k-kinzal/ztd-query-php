<?php

declare(strict_types=1);

namespace SqlCatalog\Facade;

use SqlCatalog\Core\Reporter\SqlFormatter as FormatterContract;
use SqlCatalog\Core\Sql\Dialects;
use SqlCatalog\Platform;
use SqlCatalog\Reporter\Html\SqlFormatter;

/**
 * Composition of the implementations shipped by the package.
 *
 * @visibility root
 */
final class Builtins
{
    /**
     * The built-in SQL policies and known framework connection classes.
     */
    public static function dialects(): Dialects
    {
        return new Dialects([
            'mysql' => new Platform\MySql\Dialect(),
            'pgsql' => new Platform\PostgreSql\Dialect(),
            'sqlite' => new Platform\Sqlite\Dialect(),
        ], [
            'Illuminate\Database\MySqlConnection' => 'mysql',
            'Illuminate\Database\PostgresConnection' => 'pgsql',
            'Illuminate\Database\SQLiteConnection' => 'sqlite',
        ]);
    }

    /**
     * @return list<FormatterContract> Independent syntax presentation policies
     */
    public static function formatters(): array
    {
        return [new Platform\MySql\SqlFormatter(), new Platform\PostgreSql\SqlFormatter(), new Platform\Sqlite\SqlFormatter()];
    }

    /**
     * HTML formatting with gap metadata retained across all built-in grammars.
     */
    public static function sqlFormatter(): SqlFormatter
    {
        return new SqlFormatter(self::formatters());
    }
}
