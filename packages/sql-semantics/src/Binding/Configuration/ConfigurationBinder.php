<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds configuration assignments and reset requests against the current scope.
 * @visibility SqlSemantics
 */
final class ConfigurationBinder
{
    /**
     * Returns a SET or RESET operation with its explicit assignment form.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, string $kind, Scope $scope): ?BoundStatement
    {
        if ($kind === 'SET') {
            $transaction = TransactionSettings::bind($origin, $statement);
            if ($transaction !== null) {
                return $transaction;
            }
            $password = Password\PasswordBinder::bind($origin, $statement, $scope);
            if ($password !== null) {
                return $password;
            }
            $role = Role\RoleBinder::bind($origin, $statement, $scope->identifiers);
            if ($role !== null) {
                return $role;
            }
            $settings = [];
            foreach ((new SettingBinder())->bind($statement, $scope) as $setting) {
                if (!$setting instanceof \SqlSemantics\Model\Configuration\DefaultSetting && !$setting instanceof \SqlSemantics\Model\Configuration\AssignedUserVariable && !$setting instanceof \SqlSemantics\Model\Configuration\AssignedSetting && !$setting instanceof \SqlSemantics\Model\Configuration\CurrentSetting) {
                    throw new UnclassifiedSql('SET requires an assignment or a copy from the current value.');
                }
                $settings[] = $setting;
            }
            return new \SqlSemantics\Model\Statement\Configuration\SetStatement($origin, $settings);
        }
        if ($kind === 'RESET') {
            return ResetBinder::bind($origin, $statement, $scope);
        }
        return null;
    }
}
