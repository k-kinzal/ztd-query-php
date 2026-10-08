<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

/**
 * The storage engines, plugins, privileges, character set descriptions and collation sort lengths of the emulated server.
 *
 * They are those of a MySQL 8.4.7 server without extra plugins, as resources/server.php holds
 * them. A storage engine is named by its name or one of its aliases, without regard to case;
 * one whose support is NO is disabled and unknown to the statements that name an engine.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/storage-engines.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-plugins.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-privileges.html.
 *
 * @visibility MySqlMemory
 */
final class ServerCatalog
{
    /**
     * The other names of the storage engines, by lower-case alias.
     */
    public const ALIASES = ['innobase' => 'InnoDB', 'heap' => 'MEMORY', 'merge' => 'MRG_MYISAM'];

    private static ?self $instance = null;

    /**
     * @param list<array{string, string, string, string|null, string|null, string|null}> $engines The rows of SHOW ENGINES
     * @param list<array{string, string, string, string|null, string}> $plugins The rows of SHOW PLUGINS
     * @param list<array{string, string, string}> $privileges The rows of SHOW PRIVILEGES
     * @param array<string, string> $charsets The description of each character set, by name
     * @param array<string, int> $collations The sort length of each collation, by name
     */
    public function __construct(public readonly array $engines, public readonly array $plugins, public readonly array $privileges, public readonly array $charsets, public readonly array $collations)
    {
    }

    /**
     * Answers the catalog of the emulated server.
     */
    public static function shared(): self
    {
        if (self::$instance === null) {
            /** @var array{engines: list<array{string, string, string, string|null, string|null, string|null}>, plugins: list<array{string, string, string, string|null, string}>, privileges: list<array{string, string, string}>, charsets: array<string, string>, collations: array<string, int>} $catalog */
            $catalog = require dirname(__DIR__, 4) . '/resources/server.php';
            self::$instance = new self($catalog['engines'], $catalog['plugins'], $catalog['privileges'], $catalog['charsets'], $catalog['collations']);
        }

        return self::$instance;
    }

    /**
     * Answers the catalog of a release: that of 8.4.7, with the plugins and privileges the release has.
     *
     * The mysql_native_password plugin is active, and listed second, before MySQL 8.4, which disables it by default, and
     * gone from 9.0, which removed it; a MySQL 8.0 server lists the privileges
     * resources/privileges-8.0.php holds (verified on live 8.0, 8.4 and 9.1 servers).
     * Source: https://dev.mysql.com/doc/relnotes/mysql/8.4/en/news-8-4-0.html,
     * https://dev.mysql.com/doc/relnotes/mysql/9.0/en/news-9-0-0.html.
     *
     * @param string $version The release, as `8.0.44`
     */
    public static function of(string $version): self
    {
        $shared = self::shared();
        $before = static fn (string $first): bool => version_compare($version, $first, '<');
        if (!$before('8.4.0') && $before('9.0.0')) {
            return $shared;
        }
        $plugins = [];
        foreach ($shared->plugins as $plugin) {
            if ($plugin[0] !== 'mysql_native_password') {
                $plugins[] = $plugin;
            } elseif ($before('8.4.0')) {
                array_splice($plugins, 1, 0, [[$plugin[0], 'ACTIVE', $plugin[2], $plugin[3], $plugin[4]]]);
            }
        }
        $privileges = $shared->privileges;
        if (str_starts_with($version, '8.0.')) {
            /** @var list<array{string, string, string}> $privileges */
            $privileges = require dirname(__DIR__, 4) . '/resources/privileges-8.0.php';
        }

        return new self($shared->engines, $plugins, $privileges, $shared->charsets, $shared->collations);
    }

    /**
     * Answers the name of the enabled storage engine a name or alias names, or null when none is.
     */
    public function engine(string $name): ?string
    {
        $wanted = self::ALIASES[strtolower($name)] ?? $name;
        foreach ($this->engines as [$engine, $support]) {
            if (strcasecmp($engine, $wanted) === 0) {
                return $support === 'NO' ? null : $engine;
            }
        }

        return null;
    }
}
