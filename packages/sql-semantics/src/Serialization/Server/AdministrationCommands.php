<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Administration;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Session\Roles;
use SqlSemantics\Serialization\Settings;

/**
 * Writes component, clone and instance administration commands from their operands.
 * @visibility SqlSemantics
 */
final class AdministrationCommands
{
    /**
     * Writes INSTALL COMPONENT or UNINSTALL COMPONENT with every URN and assignment.
     */
    public static function components(Administration\InstallComponentStatement|Administration\UninstallComponentStatement $statement): Tree
    {
        $urns = Build::separated(array_map(Expressions::write(...), $statement->components));
        if ($statement instanceof Administration\UninstallComponentStatement) {
            return new Tree('uninstall-component', [Build::keyword('UNINSTALL COMPONENT'), $urns]);
        }
        $settings = $statement->settings === [] ? [] : [Build::keyword('SET'), Build::separated(array_map(static fn (AssignedSetting $setting): Tree => Settings::assignment($setting, Dialect::MySql), $statement->settings))];
        return new Tree('install-component', [Build::keyword('INSTALL COMPONENT'), $urns, ...$settings]);
    }

    /**
     * Writes the ALTER INSTANCE action; the mysql_main channel is the default and is not spelled.
     */
    public static function instance(Administration\RotateMasterKeyStatement|Administration\ReloadTlsStatement|Administration\ReloadKeyringStatement|Administration\AlterRedoLogStatement $statement): Tree
    {
        $action = match (true) {
            $statement instanceof Administration\RotateMasterKeyStatement => [Build::keyword('ROTATE ' . $statement->scope->value . ' MASTER KEY')],
            $statement instanceof Administration\ReloadKeyringStatement => [Build::keyword('RELOAD KEYRING')],
            $statement instanceof Administration\AlterRedoLogStatement => [Build::keyword(($statement->enabled ? 'ENABLE' : 'DISABLE') . ' INNODB REDO_LOG')],
            $statement instanceof Administration\ReloadTlsStatement => [
                Build::keyword('RELOAD TLS'),
                ...($statement->channel === TlsChannel::Main ? [] : [Build::keyword('FOR CHANNEL'), Build::identifier([$statement->channel->value], Dialect::MySql)]),
                ...($statement->rollbackOnError ? [] : [Build::keyword('NO ROLLBACK ON ERROR')]),
            ],
        };
        return new Tree('alter-instance', [Build::keyword('ALTER INSTANCE'), ...$action]);
    }

    /**
     * Writes CLONE INSTANCE FROM with the donor, credentials, destination and encryption requirement.
     */
    public static function clone(Administration\CloneRemoteStatement $statement): Tree
    {
        $donor = $statement->donor instanceof CurrentAccount ? Build::keyword('CURRENT_USER') : Roles::accounts([$statement->donor]);
        return new Tree('clone-instance', [
            Build::keyword('CLONE INSTANCE FROM'),
            $donor,
            Build::keyword(':'),
            Expressions::write($statement->port),
            Build::keyword('IDENTIFIED BY'),
            Expressions::write($statement->password),
            ...($statement->directory === null ? [] : [Build::keyword('DATA DIRECTORY ='), Expressions::write($statement->directory)]),
            ...($statement->encryption === null ? [] : [Build::keyword($statement->encryption->value)]),
        ]);
    }
}
