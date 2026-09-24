<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\Password as Statement;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Settings;

/**
 * Writes credential operations without invoking password generation, hashing, or authentication.
 * @visibility SqlSemantics
 */
final class Passwords
{
    /**
     * Writes a single request or the ordered clauses of a MySQL 5.6 account SET; a session item after a scoped item spells SESSION, because the server carries the earlier scope over.
     */
    public static function write(Statement\SetPasswordStatement|Statement\SetPasswordHashStatement|Statement\SetDerivedPasswordStatement|Statement\SetRandomPasswordStatement|Statement\SetAccountOptionsStatement $statement): Tree
    {
        if (!$statement instanceof Statement\SetAccountOptionsStatement) {
            return new Tree('set-password', [Build::keyword('SET'), self::clause($statement)]);
        }
        $clauses = [];
        $carried = \SqlSemantics\Model\Configuration\SettingScope::Session;
        foreach ($statement->operations as $operation) {
            if (!$operation instanceof SetStatement) {
                $clauses[] = self::clause($operation);
                continue;
            }
            $setting = $operation->settings[0];
            $clauses[] = Settings::assignment($setting, $operation->origin->dialect, $carried);
            if ($setting instanceof \SqlSemantics\Model\Configuration\AssignedSetting || $setting instanceof \SqlSemantics\Model\Configuration\DefaultSetting) {
                $carried = $setting->scope;
            }
        }
        return new Tree('account-set', [Build::keyword('SET'), Build::separated($clauses)]);
    }

    /**
     * Each credential form has exactly its required value or generation policy.
     */
    public static function clause(Statement\SetPasswordStatement|Statement\SetPasswordHashStatement|Statement\SetDerivedPasswordStatement|Statement\SetRandomPasswordStatement $statement): Tree
    {
        $target = $statement->account instanceof AccountName ? [Build::keyword('FOR'), Roles::accounts([$statement->account])] : [];
        $value = match (true) {
            $statement instanceof Statement\SetPasswordStatement => new Tree('password-input', [Build::keyword('='), Expressions::write($statement->password)]),
            $statement instanceof Statement\SetPasswordHashStatement => new Tree('password-hash', [Build::keyword('='), Expressions::write($statement->hash)]),
            $statement instanceof Statement\SetDerivedPasswordStatement => new Tree('password-derivation', [Build::keyword('='), Build::keyword($statement->derivation->value), Build::parentheses(Expressions::write($statement->password))]),
            $statement instanceof Statement\SetRandomPasswordStatement => Build::keyword('TO RANDOM'),
        };
        $options = [];
        if ($statement instanceof Statement\SetPasswordStatement || $statement instanceof Statement\SetRandomPasswordStatement) {
            if ($statement->currentPassword !== null) {
                $options = [Build::keyword('REPLACE'), Expressions::write($statement->currentPassword)];
            }
            if ($statement->retainCurrentPassword) {
                $options[] = Build::keyword('RETAIN CURRENT PASSWORD');
            }
        }
        return new Tree('password-clause', [Build::keyword('PASSWORD'), ...$target, $value, ...$options]);
    }
}
