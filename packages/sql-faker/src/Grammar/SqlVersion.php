<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Binds the grammar and lexical profile generated for one SQL implementation version.
 */
final class SqlVersion
{
    private function __construct(
        public readonly string $dialect,
        public readonly string $name,
        public readonly string $astPath,
        public readonly string $lexicalPath,
    ) {
    }

    public static function resolve(string $dialect, ?string $version = null): self
    {
        $registry = self::registry();
        $dialectDefinition = $registry[$dialect] ?? null;
        if ($dialectDefinition === null) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }
        $version ??= $dialectDefinition['default'];
        $resources = $dialectDefinition['versions'][$version] ?? null;
        if ($resources === null) {
            throw new RuntimeException("Unsupported {$dialect} version: {$version}");
        }

        return new self(
            $dialect,
            $version,
            self::resourcePath($resources['ast']),
            self::resourcePath($resources['lexical']),
        );
    }

    /**
     * @return list<string>
     */
    public static function names(string $dialect): array
    {
        $registry = self::registry();
        if (!isset($registry[$dialect])) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }

        return array_keys($registry[$dialect]['versions']);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $versions = [];
        foreach (array_keys(self::registry()) as $dialect) {
            foreach (self::names($dialect) as $version) {
                $versions[] = self::resolve($dialect, $version);
            }
        }

        return $versions;
    }

    /**
     * @return array<string, array{default: string, versions: array<string, array{ast: string, lexical: string}>}>
     */
    private static function registry(): array
    {
        /** @var array<string, array{default: string, versions: array<string, array{ast: string, lexical: string}>}> $registry */
        $registry = require self::resourceDirectory() . '/version.php';

        return $registry;
    }

    private static function resourcePath(string $relativePath): string
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            throw new RuntimeException("Invalid SQL version resource path: {$relativePath}");
        }

        return self::resourceDirectory() . '/' . $relativePath;
    }

    private static function resourceDirectory(): string
    {
        return dirname(__DIR__, 2) . '/resources';
    }
}
