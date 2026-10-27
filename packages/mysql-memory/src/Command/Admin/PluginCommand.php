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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

/**
 * Executes INSTALL PLUGIN, UNINSTALL PLUGIN, INSTALL COMPONENT and UNINSTALL COMPONENT.
 *
 * Each commits the open transaction. The emulated server has only its built-in plugins and no
 * library in its plugin directory, so nothing can be installed: a plugin name already taken is
 * ER_UDF_EXISTS, a library named with a path or with more than 64 characters is ER_UDF_NO_PATHS,
 * and any other library cannot be opened (ER_CANT_OPEN_LIBRARY). A built-in plugin cannot be uninstalled (ER_PLUGIN_IS_PERMANENT)
 * and another one does not exist. MySQL 5.6 warns about a built-in plugin and then reports it missing. A component URN without a scheme is ER_COMPONENTS_NO_SCHEME, a
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        $session->transaction->commit();
        $directory = (string) $session->variables->read('plugin_dir');
        if ($statement instanceof CreateLoadableFunction) {
            $this->loadable($statement, $session);
        }
        if ($statement instanceof InstallPlugin) {
            if ($this->builtIn($statement->plugin->value, $session->instance->version)) {
                throw AdministrationError::FunctionExists->error($statement->plugin->value);
            }
            $library = (new Literals())->bytes($statement->library);
            if (str_contains($library, '/') || mb_strlen($library) > 64) {
                throw AdministrationError::PathsForbidden->error();
            }
            throw $this->unopened($directory, $library, $session->settings()->legacy() || in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql810, \SqlSemantics\Contract\GrammarRelease::MySql820, \SqlSemantics\Contract\GrammarRelease::MySql830], true) ? '2' : '11');
        }
        if ($statement instanceof UninstallPlugin) {
            if ($this->builtIn($statement->plugin->value, $session->instance->version)) {
                if (!str_starts_with($session->instance->version, '5.6.')) {
                    throw AdministrationError::PermanentPlugin->error();
                }
                $session->diagnostics->warning(AdministrationError::PermanentPlugin, AdministrationError::PermanentPlugin->message());
            }
            throw ProgramError::RoutineMissing->error('PLUGIN', $statement->plugin->value);
        }
        if ($statement instanceof InstallComponent) {
            (new Plugin\Settings())->prepare($operation, $session, $context, $connection);
            foreach ($statement->components as $component) {
                $this->load((new Literals())->bytes($component), $directory);
            }
        }
        if ($statement instanceof UninstallComponent) {
            $urns = array_map(fn ($component): string => $this->urn((new Literals())->bytes($component)), $statement->components);
            foreach ($urns as $urn) {
                $session->diagnostics->warning(AdministrationError::ComponentNotPersisted, \MySqlMemory\Value\Diagnostic\Encoding::repair(AdministrationError::ComponentNotPersisted->message($urn)));
            }
            throw AdministrationError::ComponentNotLoaded->error($urns[0]);
        }
        throw StatementError::NotSupportedYet->error('this plugin statement');
    }

    /**
     * Refuses a native function name or a path, then reports the absent function library.
     * Function library errors name the requested library and limit the operating-system detail
     * to 128 bytes. The emulator has no loadable native libraries.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/create-function-loadable.html.
     *
     * @throws SqlError Always, because the library cannot be loaded
     */
    public function loadable(CreateLoadableFunction $statement, Session $session): never
    {
        if ($statement->nameCollision($session->settings()->release())) {
            throw ($statement->ifNotExists ? AdministrationError::NativeFunctionIfNotExists : AdministrationError::NativeFunctionName)->error($statement->name->value);
        }
        $library = (new Literals())->bytes($statement->library);
        if (str_contains($library, '/')) {
            throw AdministrationError::PathsForbidden->error();
        }
        $directory = (string) $session->variables->read('plugin_dir');

        throw $this->unopened($directory, $library, '11', false);
    }

    /**
     * Tells whether a plugin name, without regard to letter case, is that of a built-in plugin.
     */
    public function builtIn(string $name, string $version = '8.4.7'): bool
    {
        foreach (ServerCatalog::of($version)->plugins as [$plugin]) {
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
        $urn = explode("\0", $urn, 2)[0];
        $display = $this->urn($urn);
        $separator = strpos($urn, '://');
        if ($separator === false) {
            throw AdministrationError::ComponentSchemeMissing->error($display);
        }
        $scheme = substr($urn, 0, $separator);
        if ($scheme !== 'file') {
            throw AdministrationError::ComponentSchemeUnserviced->error($this->urn($scheme), $display);
        }
        $path = substr($urn, $separator + 3);
        if (str_contains($path, '/')) {
            throw AdministrationError::ComponentCantLoad->error($display);
        }
        $unopened = $this->unopened($directory, $path . '.so');

        throw new SqlError($unopened->error, $unopened->getMessage(), null, [[AdministrationError::ComponentCantLoad->value, AdministrationError::ComponentCantLoad->message($display)]]);
    }

    /**
     * Bounds a displayed component URN to 192 bytes and stops at its first NUL.
     * The full semantic operand remains available for library lookup.
     */
    public function urn(string $urn): string
    {
        return substr(explode("\0", $urn, 2)[0], 0, 192);
    }

    /**
     * Answers the error of a library the plugin directory does not hold, bounding the operating-system detail to 128 bytes; an empty name, `.` and `..` name a directory.
     *
     * @param bool $qualified Whether the error names the full plugin path; function errors name the requested library
     * @param string $errno The error number the server reports for the failed open: 11 from 8.0 on, 2 in 5.6 and 5.7 (verified on live 5.7.44 and 8.4.7 servers)
     */
    public function unopened(string $directory, string $library, string $errno = '11', bool $qualified = true): SqlError
    {
        $path = $directory . $library;
        $reason = in_array($library, ['', '.', '..'], true) ? 'cannot read file data: Is a directory' : 'cannot open shared object file: No such file or directory';

        $detail = $path . ': ' . $reason;

        return AdministrationError::CantOpenLibrary->error(substr($qualified ? $path : $library, 0, 192), $errno, substr($detail, 0, 128));
    }
}
