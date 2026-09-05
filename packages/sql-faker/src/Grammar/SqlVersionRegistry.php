<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Answers which SQL implementation versions this package ships artifacts for.
 *
 * A grammar AST and a lexical profile are generated per release and committed
 * under `resources`, and `resources/version.php` is the only record of which
 * releases were generated and where each pair landed. Reading that record is a
 * separate concern from being one entry in it, so the registry answers for the
 * catalogue and SqlVersion carries a single entry.
 *
 * @visibility root
 */
final class SqlVersionRegistry
{
    /**
     * Answers the artifacts generated for one release, defaulting to the newest the dialect ships.
     *
     * @param string $dialect Dialect the caller generates SQL for
     * @param string|null $version Release to resolve, or null for the dialect default
     *
     * @return SqlVersion Artifacts committed for that release
     *
     * @throws RuntimeException When the dialect or the release is not one this package ships
     */
    public function resolve(string $dialect, ?string $version = null): SqlVersion
    {
        $registry = $this->entries();
        $dialectDefinition = $registry[$dialect] ?? null;
        if ($dialectDefinition === null) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }
        $version ??= $dialectDefinition['default'];
        $resources = $dialectDefinition['versions'][$version] ?? null;
        if ($resources === null) {
            throw new RuntimeException("Unsupported {$dialect} version: {$version}");
        }

        return new SqlVersion(
            $dialect,
            $version,
            $this->path($resources['ast']),
            $this->path($resources['lexical']),
        );
    }

    /**
     * Answers every release one dialect ships, oldest first.
     *
     * @param string $dialect Dialect to enumerate
     *
     * @return list<string> Release names in the order they were registered
     *
     * @throws RuntimeException When the dialect is not one this package ships
     */
    public function names(string $dialect): array
    {
        $registry = $this->entries();
        if (!isset($registry[$dialect])) {
            throw new RuntimeException("Unknown SQL dialect: {$dialect}");
        }

        return array_keys($registry[$dialect]['versions']);
    }

    /**
     * Answers every release of every dialect, so a caller can act on all of them.
     *
     * @return list<SqlVersion> Artifacts committed for each registered release
     *
     * @throws RuntimeException When the record names a release it does not describe
     */
    public function all(): array
    {
        $versions = [];
        foreach (array_keys($this->entries()) as $dialect) {
            foreach ($this->names($dialect) as $version) {
                $versions[] = $this->resolve($dialect, $version);
            }
        }

        return $versions;
    }

    /**
     * Reads the record of which releases were generated.
     *
     * @return array<string, array{default: string, versions: array<string, array{ast: string, lexical: string}>}> Releases by dialect
     */
    public function entries(): array
    {
        /** @var array<string, array{default: string, versions: array<string, array{ast: string, lexical: string}>}> $registry */
        $registry = require $this->directory() . '/version.php';

        return $registry;
    }

    /**
     * Resolves an artifact path recorded in the registry against the resource directory.
     *
     * The record is data the package reads at runtime, so a path that escapes
     * the resource directory would let it load a file the package never
     * generated. Only relative paths that stay inside are accepted.
     *
     * @param string $relativePath Path as the registry records it
     *
     * @return string Absolute path of the artifact
     *
     * @throws RuntimeException When the path is absolute, empty, or climbs out of the resource directory
     */
    public function path(string $relativePath): string
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            throw new RuntimeException("Invalid SQL version resource path: {$relativePath}");
        }

        return $this->directory() . '/' . $relativePath;
    }

    /**
     * Answers the directory generated artifacts are committed under.
     *
     * @return string Absolute path of the resource directory
     */
    public function directory(): string
    {
        return dirname(__DIR__, 2) . '/resources';
    }
}
