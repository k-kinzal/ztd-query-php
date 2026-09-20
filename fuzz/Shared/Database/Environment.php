<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

/**
 * Connection settings for the native oracle.
 */
final class Environment
{
    /**
     * Read a connection setting without treating the string zero as missing.
     */
    public static function read(string $name, string $fallback): string
    {
        $value = getenv($name);
        return $value === false ? $fallback : $value;
    }

}
