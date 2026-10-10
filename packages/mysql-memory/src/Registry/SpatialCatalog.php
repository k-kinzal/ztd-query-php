<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The spatial reference systems of the server: those it defines when it is installed, and those CREATE SPATIAL REFERENCE SYSTEM adds.
 *
 * The installed systems are those of resources/spatial-reference-systems.php. The name of a
 * system is unique without regard to letter case. SRIDs from 0 to 32767 are reserved for the
 * systems of the server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialCatalog
{
    /**
     * The highest SRID of the range reserved for the systems of the server.
     */
    public const RESERVED = 32767;

    /**
     * @var array<int, string>|null The name of each installed system, by SRID, once read
     */
    private static ?array $installed = null;

    /**
     * @var array<string, int>|null The SRID of each installed system, by the key of its name, once computed
     */
    private static ?array $keys = null;

    /**
     * @var array<int, string|null> The name of each system changed since the server started, by SRID; null for one that was dropped
     */
    public array $changed = [];

    /**
     * Answers the name of each system the server defines when it is installed, by SRID.
     *
     * @return array<int, string>
     */
    public static function installed(): array
    {
        if (self::$installed === null) {
            /** @var array<int, string> $names */
            $names = require dirname(__DIR__, 2) . '/resources/spatial-reference-systems.php';
            self::$installed = $names;
        }

        return self::$installed;
    }

    /**
     * Answers the SRID of each installed system, by the key of its name.
     *
     * @return array<string, int>
     */
    public static function keys(): array
    {
        if (self::$keys === null) {
            self::$keys = [];
            foreach (self::installed() as $srid => $name) {
                self::$keys[Registry::key($name)] ??= $srid;
            }
        }

        return self::$keys;
    }

    /**
     * Answers the name of the system of an SRID, or null when there is none.
     */
    public function name(int $srid): ?string
    {
        if (array_key_exists($srid, $this->changed)) {
            return $this->changed[$srid];
        }

        return self::installed()[$srid] ?? null;
    }

    /**
     * Answers the SRID of another system with a name, without regard to letter case and accents, or null.
     */
    public function named(string $name, int $except): ?int
    {
        $wanted = Registry::key($name);
        foreach ($this->changed as $srid => $changed) {
            if ($srid !== $except && $changed !== null && Registry::key($changed) === $wanted) {
                return $srid;
            }
        }
        $srid = self::keys()[$wanted] ?? null;

        return $srid !== null && $srid !== $except && !array_key_exists($srid, $this->changed) ? $srid : null;
    }

    /**
     * Defines or replaces the system of an SRID.
     */
    public function define(int $srid, string $name): void
    {
        $this->changed[$srid] = $name;
    }

    /**
     * Removes the system of an SRID.
     */
    public function drop(int $srid): void
    {
        $this->changed[$srid] = null;
    }
}
