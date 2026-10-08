<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;
use SqlSemantics\Statement\Operation;

/**
 * Executes INSTALL PLUGIN, UNINSTALL PLUGIN, INSTALL COMPONENT and UNINSTALL COMPONENT.
 *
 * Each commits the open transaction. The emulated server has only its built-in plugins and no
 * library in its plugin directory, so nothing can be installed: a plugin name already taken is
 * ER_UDF_EXISTS, a library named with a path or with more than 64 characters is ER_UDF_NO_PATHS,
 * and any other library cannot be opened (ER_CANT_OPEN_LIBRARY). A built-in plugin cannot be uninstalled (ER_PLUGIN_IS_PERMANENT)
 * and another one does not exist. A component URN without a scheme is ER_COMPONENTS_NO_SCHEME, a
 * scheme other than file is ER_COMPONENTS_NO_SCHEME_SERVICE, and a file:// URN names the library
 * of its path with `.so` added, which cannot be opened either. UNINSTALL COMPONENT warns that each
 * component was not loaded by the persistent loader, then fails for the first (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-plugin.html,
 * https://dev.mysql.com/doc/refman/8.4/en/uninstall-plugin.html,
 * https://dev.mysql.com/doc/refman/8.4/en/install-component.html,
 * https://dev.mysql.com/doc/refman/8.4/en/uninstall-component.html.
 *
 * @visibility MySqlMemory
 */
final class PluginCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Refuses to install or uninstall the plugin or component, as the server does.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        $directory = (string) $session->variables->read('plugin_dir');
        if ($statement instanceof InstallPlugin) {
            if ($this->builtIn($statement->plugin->value)) {
                throw AdministrationError::FunctionExists->error($statement->plugin->value);
            }
            $library = (new Literals())->bytes($statement->library);
            if (str_contains($library, '/') || mb_strlen($library) > 64) {
                throw AdministrationError::PathsForbidden->error();
            }
            throw $this->unopened($directory, $library, $session->settings()->legacy() ? '2' : '11');
        }
        if ($statement instanceof UninstallPlugin) {
            throw $this->builtIn($statement->plugin->value) ? AdministrationError::PermanentPlugin->error() : ProgramError::RoutineMissing->error('PLUGIN', $statement->plugin->value);
        }
        if ($statement instanceof InstallComponent) {
            foreach ($statement->components as $component) {
                $this->load((new Literals())->bytes($component), $directory);
            }
        }
        if ($statement instanceof UninstallComponent) {
            $urns = array_map(static fn ($component): string => (new Literals())->bytes($component), $statement->components);
            foreach ($urns as $urn) {
                $session->diagnostics->warning(AdministrationError::ComponentNotPersisted, AdministrationError::ComponentNotPersisted->message($urn));
            }
            throw AdministrationError::ComponentNotLoaded->error($urns[0] ?? '');
        }
        throw StatementError::NotSupportedYet->error('this plugin statement');
    }

    /**
     * Tells whether a plugin name, without regard to letter case, is that of a built-in plugin.
     */
    public function builtIn(string $name): bool
    {
        foreach (ServerCatalog::shared()->plugins as [$plugin]) {
            if (strcasecmp($plugin, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fails to load the component of a URN.
     *
     * @throws SqlError Always: the URN has no scheme, a scheme without a loader, or no library
     */
    public function load(string $urn, string $directory): void
    {
        $separator = strpos($urn, '://');
        if ($separator === false) {
            throw AdministrationError::ComponentSchemeMissing->error($urn);
        }
        $scheme = substr($urn, 0, $separator);
        if ($scheme !== 'file') {
            throw AdministrationError::ComponentSchemeUnserviced->error($scheme, $urn);
        }
        $path = substr($urn, $separator + 3);
        if (str_contains($path, '/')) {
            throw AdministrationError::ComponentCantLoad->error($urn);
        }
        $unopened = $this->unopened($directory, $path . '.so');

        throw new SqlError($unopened->error, $unopened->getMessage(), null, [[AdministrationError::ComponentCantLoad->value, AdministrationError::ComponentCantLoad->message($urn)]]);
    }

    /**
     * Answers the error of a library the plugin directory does not hold; an empty name, `.` and `..` name a directory.
     *
     * @param string $errno The error number the server reports for the failed open: 11 from 8.0 on, 2 in 5.6 and 5.7 (verified on live 5.7.44 and 8.4.7 servers)
     */
    public function unopened(string $directory, string $library, string $errno = '11'): SqlError
    {
        $path = $directory . $library;
        $reason = in_array($library, ['', '.', '..'], true) ? 'cannot read file data: Is a directory' : 'cannot open shared object file: No such file or directory';

        return AdministrationError::CantOpenLibrary->error($path, $errno, $path . ': ' . $reason);
    }
}
