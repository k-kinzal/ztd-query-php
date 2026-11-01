<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The spatial reference systems of the server: those it defines when it is installed, and those CREATE SPATIAL REFERENCE SYSTEM adds.
 *
 * The installed systems are owned by the versioned coordinate-system definitions. The name of a
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
     * @var array<string, array<int, array{string, ?string, ?int, string, ?string}>> Initial catalogs cached by generation
     */
    private static array $catalogs = [];

    /**
     * @var array<string, array<string, int>> Name indexes cached by generation
     */
    private static array $keys = [];

    /**
     * Uses the installed catalog of the emulated release.
     */
    public function __construct(public readonly string $version = '8.4.7')
    {
    }

    /**
     * @var array<int, string|null> The name of each system changed since the server started, by SRID; null for one that was dropped
     */
    public array $changed = [];

    /**
     * @var array<int, array{?string, ?int, string, ?string}> Organization, organization ID, definition and description of replaced systems
     */
    public array $details = [];

    /**
     * Answers the name of each system the server defines when it is installed, by SRID.
     *
     * @return array<int, string>
     */
    public static function installed(string $version = '8.4.7'): array
    {
        return array_map(static fn (array $record): string => $record[0], self::data($version));
    }

    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}> Initial SQL catalog records by SRID
     */
    public static function data(string $version = '8.4.7'): array
    {
        $generation = version_compare($version, '8.1.0', '<') ? '8.0' : '8.1';
        if (!isset(self::$catalogs[$generation])) {
            $records = Spatial\InstalledCoordinates::of(\SqlSemantics\Contract\GrammarRelease::from('mysql-' . $version));
            self::$catalogs[$generation] = $records;
        }
        return self::$catalogs[$generation];
    }

    /**
     * Answers the SRID of each installed system, by the key of its name.
     *
     * @return array<string, int>
     */
    public static function keys(string $version = '8.4.7'): array
    {
        $generation = version_compare($version, '8.1.0', '<') ? '8.0' : '8.1';
        if (!isset(self::$keys[$generation])) {
            self::$keys[$generation] = [];
            foreach (self::installed($version) as $srid => $name) {
                self::$keys[$generation][Registry::key($name)] ??= $srid;
            }
        }
        return self::$keys[$generation];
    }

    /**
     * Answers the name of the system of an SRID, or null when there is none.
     */
    public function name(int $srid): ?string
    {
        if (array_key_exists($srid, $this->changed)) {
            return $this->changed[$srid];
        }

        return self::data($this->version)[$srid][0] ?? null;
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
        $srid = self::keys($this->version)[$wanted] ?? null;

        return $srid !== null && $srid !== $except && !array_key_exists($srid, $this->changed) ? $srid : null;
    }

    /**
     * Defines or replaces the system of an SRID.
     */
    public function define(int $srid, string $name, ?string $organization = null, ?int $organizationId = null, string $definition = '', ?string $description = null): void
    {
        $this->changed[$srid] = $name;
        $this->details[$srid] = [$organization, $organizationId, $definition, $description];
    }

    /**
     * Removes the system of an SRID.
     */
    public function drop(int $srid): void
    {
        $this->changed[$srid] = null;
        unset($this->details[$srid]);
    }

    /**
     * Finds another system with the same non-null organization and identifier.
     */
    public function organization(?string $organization, ?int $identifier, int $except): ?int
    {
        if ($organization === null || $identifier === null) {
            return null;
        }
        $key = Registry::key($organization);
        foreach ($this->details as $srid => [$other, $id]) {
            if ($srid !== $except && $other !== null && Registry::key($other) === $key && $id === $identifier) {
                return $srid;
            }
        }
        return $key === 'EPSG' && $identifier !== 0 && $identifier !== $except && isset(self::data($this->version)[$identifier]) && !array_key_exists($identifier, $this->changed) ? $identifier : null;
    }

    /**
     * @return array{?string, ?int, string, ?string} The retained attributes, or the installed catalog defaults
     */
    public function attributes(int $srid): array
    {
        [, $organization, $identifier, $definition, $description] = self::data($this->version)[$srid] ?? ['', null, null, '', null];
        return $this->details[$srid] ?? [$organization, $identifier, $definition, $description];
    }
}
