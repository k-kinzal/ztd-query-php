<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The plugins, keywords and storage engines INFORMATION_SCHEMA lists on a server of a release without extra plugins, as resources/server-tables holds them.
 *
 * A release without a catalog of its own has that of the latest series of its major version.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-plugins-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-schema-keywords-table.html,
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
     * @param list<array{string, int}> $keywords The rows of INFORMATION_SCHEMA.KEYWORDS
     * @param list<array{string, string, string, string|null, string|null, string|null}> $engines The rows of INFORMATION_SCHEMA.ENGINES
     */
    public function __construct(public readonly array $plugins, public readonly array $keywords, public readonly array $engines)
    {
    }

    /**
     * Answers the catalog of a release.
     */
    public static function of(GrammarRelease $release): self
    {
        if (!isset(self::$releases[$release->value])) {
            $directory = dirname(__DIR__, 3) . '/resources/server-tables/';
            $files = glob($directory . substr($release->value, 0, 9) . '*.php');
            $major = glob($directory . substr($release->value, 0, 7) . '*.php');
            $file = is_array($files) && $files !== [] ? $files[0] : (is_array($major) && $major !== [] ? $major[count($major) - 1] : $directory . GrammarRelease::MySql847->value . '.php');
            /** @var array{plugins: list<array{string, string, string, string, string, string|null, string|null, string|null, string|null, string, string}>, keywords: list<array{string, int}>, engines: list<array{string, string, string, string|null, string|null, string|null}>} $catalog */
            $catalog = require $file;
            self::$releases[$release->value] = new self($catalog['plugins'], $catalog['keywords'], $catalog['engines']);
        }

        return self::$releases[$release->value];
    }
}
