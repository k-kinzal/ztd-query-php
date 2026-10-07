<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

/**
 * The system variables of one server release, read from resources/variables/mysql-RELEASE.php.
 *
 * Names are found without regard to case.
 *
 * @visibility MySqlMemory
 */
final class Catalog
{
    /**
     * @var array<string, self>
     */
    private static array $catalogs = [];

    /**
     * @param array<string, Definition> $definitions The definitions, by lower-case name
     */
    public function __construct(public readonly array $definitions)
    {
    }

    /**
     * Answers the catalog of a release, read once.
     */
    public static function release(string $version): self
    {
        if (!isset(self::$catalogs[$version])) {
            $path = dirname(__DIR__, 2) . '/resources/variables/mysql-' . $version . '.php';
            /** @var list<array{string, string, string, string|int, bool, int|null, int|null}> $entries */
            $entries = require is_file($path) ? $path : dirname(__DIR__, 2) . '/resources/variables/mysql-8.4.7.php';
            $definitions = [];
            foreach ($entries as [$name, $scope, $shape, $default, $readOnly, $minimum, $maximum]) {
                $definitions[$name] = new Definition($name, Scope::from(strtoupper($scope)), constant(Shape::class . '::' . $shape), $default, $readOnly, [], $minimum, $maximum);
            }
            self::$catalogs[$version] = new self($definitions);
        }

        return self::$catalogs[$version];
    }

    /**
     * Finds a variable by name, or answers null.
     */
    public function find(string $name): ?Definition
    {
        return $this->definitions[strtolower($name)] ?? null;
    }
}
