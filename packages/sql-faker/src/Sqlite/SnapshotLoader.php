<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotLoader as SnapshotLoaderContract;
use RuntimeException;

final class SnapshotLoader implements SnapshotLoaderContract
{
    private const AST_DIR = __DIR__ . '/../../resources/ast';
    private const AST_META = __DIR__ . '/../../resources/ast.php';

    private readonly string $resolvedVersion;

    public function __construct(
        ?string $version = null,
    ) {
        $this->resolvedVersion = self::resolveVersion($version);
    }

    public function version(): string
    {
        return $this->resolvedVersion;
    }

    public function load(): Grammar
    {
        return Grammar::loadFromFile(self::AST_DIR . '/' . $this->resolvedVersion . '.php');
    }

    private static function resolveVersion(?string $version): string
    {
        if (is_string($version) && $version !== '') {
            return $version;
        }

        /** @var array{default_sqlite?: mixed} $meta */
        $meta = require self::AST_META;
        $resolved = $meta['default_sqlite'] ?? null;
        if (!is_string($resolved) || $resolved === '') {
            throw new RuntimeException('No default SQLite version configured in ast.php');
        }

        return $resolved;
    }
}
