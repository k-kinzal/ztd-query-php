<?php

declare(strict_types=1);

namespace SqlParser\MySql;

use RuntimeException;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

/**
 * A supported MySQL release and the lexical rules that changed between releases.
 *
 * @visibility root
 */
final class MySqlVersion
{
    /**
     * The dialect name the resource record uses.
     */
    public const DIALECT = 'mysql';

    /**
     * @param SqlVersion $release The release and its resources
     */
    public function __construct(public readonly SqlVersion $release)
    {
    }

    /**
     * Resolves a release tag, the newest shipped release when none is given.
     *
     * @param string|null $version Tag such as `mysql-8.4.7`, or null for the default
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
     * @return string Tag such as `mysql-8.4.7`
     */
    public function name(): string
    {
        return $this->release->name;
    }

    /**
     * Answers the release as MySQL numbers it, as in `MYSQL_VERSION_ID`.
     *
     * @return int Major times ten thousand plus minor times a hundred plus patch
     */
    public function id(): int
    {
        $parts = explode('.', substr($this->release->name, strlen('mysql-')));

        return (int) $parts[0] * 10000 + (int) ($parts[1] ?? 0) * 100 + (int) ($parts[2] ?? 0);
    }

    /**
     * Reports whether `->` and `->>` are JSON operators, as they are from 5.7 on.
     *
     * @return bool True from 5.7 on
     */
    public function hasJsonOperators(): bool
    {
        return $this->id() >= 50700;
    }

    /**
     * Reports whether `$$ ... $$` is a string literal, as it is from 8.1 on.
     *
     * @return bool True from 8.1 on
     */
    public function hasDollarQuotedStrings(): bool
    {
        return $this->id() >= 80100;
    }

    /**
     * Reports whether `WITH CUBE` is read as one token, as it was before 8.0.
     *
     * @return bool True for 5.6 and 5.7
     */
    public function mergesWithCube(): bool
    {
        return $this->id() < 80000;
    }
}
