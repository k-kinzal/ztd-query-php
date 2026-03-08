<?php

declare(strict_types=1);

namespace SqlFakerTestSupport;

use SqlFaker\MySql\SupportedLanguage as MySqlSupportedLanguage;
use SqlFaker\PostgreSql\SupportedLanguage as PostgreSqlSupportedLanguage;

final class SupportedLanguagePool
{
    /**
     * @var array<string, MySqlSupportedLanguage>
     */
    private static array $mysql = [];

    private static ?PostgreSqlSupportedLanguage $postgresql = null;

    public static function mysql(string $version): MySqlSupportedLanguage
    {
        return self::$mysql[$version] ??= new MySqlSupportedLanguage($version);
    }

    public static function postgresql(): PostgreSqlSupportedLanguage
    {
        return self::$postgresql ??= new PostgreSqlSupportedLanguage();
    }

    public static function clear(): void
    {
        self::$mysql = [];
        self::$postgresql = null;

        gc_collect_cycles();
        gc_mem_caches();
    }
}
