<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The plugins and storage engines INFORMATION_SCHEMA lists on a server of a release without extra plugins, computed from its release definitions and grammar.
 *
 * Each supported release selects its own catalog explicitly.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-plugins-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-schema-engines-table.html.
 *
 * @visibility MySqlMemory
 */
final class ServerTables
{
    /**
     * @var array<string, self>
     */
    public static array $releases = [];

    /**
     * @param list<array{string, string, string, string, string, string|null, string|null, string|null, string|null, string, string}> $plugins The rows of INFORMATION_SCHEMA.PLUGINS
     * @param list<array{string, string, string, string|null, string|null, string|null}> $engines The rows of INFORMATION_SCHEMA.ENGINES
     */
    public function __construct(public readonly array $plugins, public readonly array $engines)
    {
    }

    /**
     * Answers the catalog of a release.
     */
    public static function of(GrammarRelease $release): self
    {
        if (!isset(self::$releases[$release->value])) {
            self::$releases[$release->value] = new self(PluginDefinitions::of($release), EngineDefinitions::of($release));
        }

        return self::$releases[$release->value];
    }
}
