<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql;

use RuntimeException;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

/**
 * A supported PostgreSQL release.
 *
 * @visibility root
 */
final class PostgreSqlVersion
{
    /**
     * The dialect name the resource record uses.
     */
    public const DIALECT = 'postgresql';

    /**
     * @param SqlVersion $release The release and its resources
     */
    public function __construct(public readonly SqlVersion $release)
    {
    }

    /**
     * Resolves a release tag, the newest shipped release when none is given.
     *
     * @param string|null $version Tag such as `pg-17.2`, or null for the default
     * @param VersionRegistry $registry Record of shipped releases
     *
     * @return self The release
     *
     * @throws RuntimeException When the release is not shipped
     */
    public static function resolve(?string $version = null, VersionRegistry $registry = new VersionRegistry()): self
    {
        return new self($registry->resolve(self::DIALECT, $version));
    }

    /**
     * Answers the release tag.
     *
     * @return string Tag such as `pg-17.2`
     */
    public function name(): string
    {
        return $this->release->name;
    }
}
