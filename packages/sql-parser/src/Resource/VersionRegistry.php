<?php

declare(strict_types=1);

namespace SqlParser\Resource;

use RuntimeException;

/**
 * Names the releases each dialect ships resources for.
 *
 * The record lives in `resources/version.php`; every path in it is relative
 * to the resource directory and may not climb out of it.
 *
 * @visibility root
 */
final class VersionRegistry
{
    /**
     * @param string|null $directory Resource directory, the package's own by default
     */
    public function __construct(private readonly ?string $directory = null)
    {
    }

    /**
     * Answers the resources of one release, the dialect's default when none is named.
     *
     * @param string $dialect Dialect to look in
     * @param string|null $version Release tag, or null for the default
     *
     * @return SqlVersion The release and its resources
     *
     * @throws RuntimeException When the dialect or the release is not shipped
     */
    public function resolve(string $dialect, ?string $version = null): SqlVersion
    {
        $entries = $this->entries();
        $definition = $entries[$dialect] ?? null;
        if ($definition === null) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }
        $version ??= $definition['default'];
        $resources = $definition['versions'][$version] ?? null;
        if ($resources === null) {
            throw new RuntimeException("Unsupported {$dialect} version: {$version}");
        }

        return new SqlVersion($dialect, $version, $this->path($resources['table']), $this->path($resources['keywords']));
    }

    /**
     * Answers every release tag of a dialect, oldest first.
     *
     * @param string $dialect Dialect to enumerate
     *
     * @return list<string> Release tags
     *
     * @throws RuntimeException When the dialect is not shipped
     */
    public function names(string $dialect): array
    {
        $entries = $this->entries();
        if (!isset($entries[$dialect])) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }

        return array_keys($entries[$dialect]['versions']);
    }

    /**
     * Reads the record of shipped releases.
     *
     * @return array<string, array{default: string, versions: array<string, array{table: string, keywords: string}>}> Releases by dialect
     */
    public function entries(): array
    {
        /** @var array<string, array{default: string, versions: array<string, array{table: string, keywords: string}>}> $entries */
        $entries = require $this->directory() . '/version.php';

        return $entries;
    }

    /**
     * Resolves a recorded path against the resource directory.
     *
     * @param string $relative Path as the record spells it
     *
     * @return string Absolute path
     *
     * @throws RuntimeException When the path is absolute, empty or climbs out of the directory
     */
    public function path(string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '..')) {
            throw new RuntimeException("Invalid resource path: {$relative}");
        }

        return $this->directory() . '/' . $relative;
    }

    /**
     * Answers the directory the resources live in.
     *
     * @return string Absolute path
     */
    public function directory(): string
    {
        return $this->directory ?? dirname(__DIR__, 2) . '/resources';
    }
}
