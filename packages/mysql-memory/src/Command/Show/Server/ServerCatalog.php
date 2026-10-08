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
