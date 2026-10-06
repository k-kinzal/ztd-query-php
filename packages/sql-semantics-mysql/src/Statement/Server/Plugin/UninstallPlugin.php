<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `UNINSTALL PLUGIN name`: a request to remove an installed server plugin.
 *
 * Mirrors Sql_cmd_uninstall_plugin. Rule: MYSQL-UNINSTALL-PLUGIN-001. The
 * statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/uninstall-plugin.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a plugin
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('uninstall plugin audit')->toString() // => 'UNINSTALL PLUGIN audit'
 */
final class UninstallPlugin implements Statement
{
    use Snapshot;

    /**
     * @param Name $plugin The plugin name
     */
    public function __construct(public readonly Name $plugin)
    {
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNINSTALL', 'PLUGIN')->name($this->plugin, NameUse::Identifier);
    }
}
