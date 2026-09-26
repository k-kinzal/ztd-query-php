<?php

declare(strict_types=1);

namespace SqlFixture\Version;

/**
 * The supported releases of each dialect and the default among them.
 *
 * The tags are the ones sql-parser, sql-semantics, sql-faker and
 * sql-formatter accept, so one tag selects the same release everywhere.
 *
 * @visibility root
 */
final class Releases
{
    /**
     * Supported releases by dialect: the default tag and the number of every tag, oldest first.
     */
    private const RELEASES = [
        'mysql' => [
            'default' => 'mysql-8.4.7',
            'versions' => [
                'mysql-5.6.51' => '5.6.51',
                'mysql-5.7.44' => '5.7.44',
                'mysql-8.0.44' => '8.0.44',
                'mysql-8.1.0' => '8.1.0',
                'mysql-8.2.0' => '8.2.0',
                'mysql-8.3.0' => '8.3.0',
                'mysql-8.4.7' => '8.4.7',
                'mysql-9.0.1' => '9.0.1',
                'mysql-9.1.0' => '9.1.0',
            ],
        ],
        'pgsql' => [
            'default' => 'pg-17.2',
            'versions' => [
                'pg-17.2' => '17.2',
            ],
        ],
        'sqlite' => [
            'default' => 'sqlite-3.47.2',
            'versions' => [
                'sqlite-3.47.2' => '3.47.2',
            ],
        ],
    ];

    /**
     * Names the default release of a dialect.
     *
     * @return string Version tag such as `mysql-8.4.7`
     *
     * @throws UnsupportedVersionException When the dialect has no releases
     */
    public function defaultTag(string $dialect): string
    {
        return $this->entry($dialect)['default'];
    }

    /**
     * Lists the releases of a dialect, oldest first.
     *
     * @return non-empty-array<string, string> Release number by version tag
     *
     * @throws UnsupportedVersionException When the dialect has no releases
     */
    public function numbers(string $dialect): array
    {
        return $this->entry($dialect)['versions'];
    }

    /**
     * @return array{default: string, versions: non-empty-array<string, string>}
     *
     * @throws UnsupportedVersionException When the dialect has no releases
     */
    public function entry(string $dialect): array
    {
        return self::RELEASES[$dialect] ?? throw new UnsupportedVersionException($dialect, null);
    }
}
