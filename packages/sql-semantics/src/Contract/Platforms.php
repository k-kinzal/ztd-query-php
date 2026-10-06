<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlSemantics\Diagnostic\Check;

/**
 * Finds the installed database package of a grammar release.
 *
 * The mapping is fixed. A package that is not installed is a configuration
 * error, not an unsupported statement.
 *
 * @visibility SqlSemantics
 */
final class Platforms
{
    /**
     * The namespace segment of each database package.
     */
    private const PACKAGES = ['mysql' => 'MySql', 'postgresql' => 'PostgreSql', 'sqlite' => 'Sqlite'];

    /**
     * @var array<string, Platform>
     */
    private static array $instances = [];

    /**
     * Answers the platform of a database family.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the database package is not installed
     */
    public static function of(string $database): Platform
    {
        if (isset(self::$instances[$database])) {
            return self::$instances[$database];
        }
        $class = self::platformClass($database);
        Check::input($class !== null && class_exists($class), 'The database package for ' . $database . ' is not installed.');
        $platform = new $class();
        Check::input($platform instanceof Platform, 'The database package for ' . $database . ' is not a platform.');

        return self::$instances[$database] = $platform;
    }

    /**
     * Names the platform class of a database family, or null for an unknown family.
     */
    public static function platformClass(string $database): ?string
    {
        $package = self::PACKAGES[$database] ?? null;

        return $package === null ? null : 'SqlSemantics\\Platform\\' . $package . '\\Platform';
    }
}
