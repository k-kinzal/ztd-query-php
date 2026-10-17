<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `INSTALL PLUGIN name SONAME 'library'`: a request to load a server plugin from a shared library.
 *
 * Mirrors Sql_cmd_install_plugin. Rule: MYSQL-INSTALL-PLUGIN-001. The
 * statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-plugin.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Installing a plugin
 *     $install = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("install plugin audit soname 'audit.so'");
 *     [$install->toString(), $install->statement->library->value] // => ["INSTALL PLUGIN audit SONAME 'audit.so'", 'audit.so']
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
        \SqlSemantics\Diagnostic\Check::input($library->radix === null, 'A plugin library name is quoted text.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
