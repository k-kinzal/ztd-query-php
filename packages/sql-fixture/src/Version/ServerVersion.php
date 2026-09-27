<?php

declare(strict_types=1);

namespace SqlFixture\Version;

/**
 * A supported database release, named by the version tag the sql-* packages share.
 *
 * @visibility public
 * @example Resolve the default release of a dialect
 *     \SqlFixture\Version\ServerVersion::resolve('mysql')->tag // => 'mysql-8.4.7'
 * @example Match the release a server reports
 *     \SqlFixture\Version\ServerVersion::fromServer('mysql', '8.0.36-0ubuntu0.22.04.1')->tag // => 'mysql-8.0.44'
 */
final class ServerVersion
{
    /**
     * @param string $dialect Dialect the release belongs to: `mysql`, `pgsql` or `sqlite`
     * @param string $tag Version tag such as `mysql-8.4.7`
     * @param string $number Release number such as `8.4.7`
     */
    public function __construct(
        public readonly string $dialect,
        public readonly string $tag,
        public readonly string $number,
    ) {
    }

    /**
     * Resolves a version tag, the default of the dialect when none is given.
     *
     * @param string $dialect Dialect to look in
     * @param string|null $tag Version tag such as `mysql-8.4.7`, or null for the default
     *
     * @throws UnsupportedVersionException When the dialect has no releases or the tag is not one of them
     */
    public static function resolve(string $dialect, ?string $tag = null): self
    {
        $releases = new Releases();
        $tag ??= $releases->defaultTag($dialect);
        $number = $releases->numbers($dialect)[$tag] ?? throw new UnsupportedVersionException($dialect, $tag);

        return new self($dialect, $tag, $number);
    }

    /**
     * Matches the release closest to the version a server reports.
     *
     * The release of the same series wins, where the series is every number
     * but the last (`8.0` for MySQL 8.0.36, `17` for PostgreSQL 17.2).
     * Otherwise the newest release that is not newer than the server wins,
     * and a server older than every release gets the oldest one.
     *
     * @param string $dialect Dialect of the server
     * @param string $reported Version string of the server, such as `8.0.36-0ubuntu0.22.04.1`
     *
     * @throws UnsupportedVersionException When the dialect has no releases or the string holds no version number
     */
    public static function fromServer(string $dialect, string $reported): self
    {
        $server = ReleaseNumber::fromReported($reported) ?? throw new UnsupportedVersionException($dialect, $reported);
        $releases = self::all($dialect);
        $sameSeries = array_filter($releases, static fn (self $release): bool => $release->series() === $server->series());
        if ($sameSeries !== []) {
            return end($sameSeries);
        }
        $notNewer = array_filter($releases, static fn (self $release): bool => $release->id() <= $server->id());

        return $notNewer !== [] ? end($notNewer) : $releases[0];
    }

    /**
     * Lists the releases of a dialect, oldest first.
     *
     * @return non-empty-list<self>
     *
     * @throws UnsupportedVersionException When the dialect has no releases
     */
    public static function all(string $dialect): array
    {
        $releases = [];
        foreach ((new Releases())->numbers($dialect) as $tag => $number) {
            $releases[] = new self($dialect, $tag, $number);
        }

        return $releases;
    }

    /**
     * Lists the version tags of a dialect, oldest first.
     *
     * @return non-empty-list<string>
     *
     * @throws UnsupportedVersionException When the dialect has no releases
     */
    public static function tags(string $dialect): array
    {
        return array_keys((new Releases())->numbers($dialect));
    }

    /**
     * Reports whether this is the default release of its dialect.
     */
    public function isDefault(): bool
    {
        return (new Releases())->defaultTag($this->dialect) === $this->tag;
    }

    /**
     * Numbers the release as MySQL numbers its own, as in `MYSQL_VERSION_ID`.
     *
     * @return int Major times ten thousand plus minor times a hundred plus patch
     */
    public function id(): int
    {
        return (new ReleaseNumber($this->number))->id();
    }

    /**
     * Names the series of the release: every number but the last.
     *
     * @return string `8.0` for `8.0.44`, `17` for `17.2`
     */
    public function series(): string
    {
        return (new ReleaseNumber($this->number))->series();
    }

    /**
     * Reports whether the release is the given number or newer.
     *
     * @param string $number Release number such as `5.7.8`
     */
    public function isAtLeast(string $number): bool
    {
        return $this->id() >= (new ReleaseNumber($number))->id();
    }
}
