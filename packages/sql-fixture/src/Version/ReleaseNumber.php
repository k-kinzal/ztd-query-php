<?php

declare(strict_types=1);

namespace SqlFixture\Version;

/**
 * A dotted release number such as `8.0.36`, and how it compares to others.
 *
 * @visibility root
 */
final class ReleaseNumber
{
    /**
     * @param string $number Release number such as `8.0.36`
     */
    public function __construct(public readonly string $number)
    {
    }

    /**
     * Reads the number a server reports, ignoring any suffix such as `-log` or a build note.
     *
     * @param string $reported Version string of the server, such as `8.0.36-0ubuntu0.22.04.1`
     *
     * @return self|null The number, or null when the string does not start with one
     */
    public static function fromReported(string $reported): ?self
    {
        return preg_match('/^\s*(\d+(?:\.\d+)*)/', $reported, $match) === 1 ? new self($match[1]) : null;
    }

    /**
     * Numbers the release as MySQL numbers its own, as in `MYSQL_VERSION_ID`.
     *
     * @return int Major times ten thousand plus minor times a hundred plus patch
     */
    public function id(): int
    {
        $parts = explode('.', $this->number);

        return (int) $parts[0] * 10000 + (int) ($parts[1] ?? 0) * 100 + (int) ($parts[2] ?? 0);
    }

    /**
     * Names the series of the release: every number but the last.
     *
     * @return string `8.0` for `8.0.36`, `17` for `17.2`, and the number itself when it has one part
     */
    public function series(): string
    {
        $parts = explode('.', $this->number);
        if (count($parts) > 1) {
            array_pop($parts);
        }

        return implode('.', $parts);
    }
}
