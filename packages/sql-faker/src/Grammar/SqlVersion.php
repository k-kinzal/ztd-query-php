<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;

/**
 * A supported SQL release and its grammar AST; lexical rules are declared in PHP.
 * @visibility root
 */
final class SqlVersion
{
    /**
     * @param string $dialect Dialect the artifacts describe
     * @param string $name Release the artifacts describe
     * @param string $astPath Where the grammar AST is read from or written to
     */
    public function __construct(
        public readonly string $dialect,
        public readonly string $name,
        public readonly string $astPath,
    ) {
    }

    /**
     * Answers the artifacts this package ships for one release.
     *
     * @param string $dialect Dialect the caller generates SQL for
     * @param string|null $version Release to resolve, or null for the dialect default
     *
     * @return self Artifacts committed for that release
     *
     * @throws RuntimeException When the dialect or the release is not one this package ships
     */
    public static function resolve(string $dialect, ?string $version = null): self
    {
        return (new SqlVersionRegistry())->resolve($dialect, $version);
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
    public static function names(string $dialect): array
    {
        return (new SqlVersionRegistry())->names($dialect);
    }

    /**
     * Answers every release of every dialect, so a caller can act on all of them.
     *
     * @return list<self> Artifacts committed for each registered release
     *
     * @throws RuntimeException When the record names a release it does not describe
     */
    public static function all(): array
    {
        return (new SqlVersionRegistry())->all();
    }
}
