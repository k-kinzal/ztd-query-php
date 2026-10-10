<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.PLUGINS: the plugins a server of the release loads without extra plugins.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-plugins-table.html.
 *
 * @visibility MySqlMemory
 */
final class Plugins implements SystemRows
{
    /**
     * Answers a row for each plugin.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $names = ['PLUGIN_NAME', 'PLUGIN_VERSION', 'PLUGIN_STATUS', 'PLUGIN_TYPE', 'PLUGIN_TYPE_VERSION', 'PLUGIN_LIBRARY', 'PLUGIN_LIBRARY_VERSION', 'PLUGIN_AUTHOR', 'PLUGIN_DESCRIPTION', 'PLUGIN_LICENSE', 'LOAD_OPTION'];

        return array_map(static fn (array $plugin): array => array_combine($names, $plugin), ServerTables::of($reading->release)->plugins);
    }
}
