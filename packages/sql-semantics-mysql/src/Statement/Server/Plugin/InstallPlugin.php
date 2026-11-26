<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * `INSTALL PLUGIN name SONAME 'library'`: a request to load a server plugin from a shared library.
 *
 * Mirrors Sql_cmd_install_plugin. Rule: MYSQL-INSTALL-PLUGIN-001. The
 * statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-plugin.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InstallPlugin implements Statement
{
    use Snapshot;

    /**
     * @param Name $plugin The plugin name
     * @param Text $library The file name of the shared library in the plugin directory
     */
    public function __construct(public readonly Name $plugin, public readonly Text $library)
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
        $out->keyword('INSTALL', 'PLUGIN')->name($this->plugin, NameUse::Identifier)->keyword('SONAME')->node($this->library);
    }
}
