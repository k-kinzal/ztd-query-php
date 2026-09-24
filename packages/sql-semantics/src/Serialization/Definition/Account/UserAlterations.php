<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes ALTER USER from its per-account changes and shared clauses.
 * @visibility SqlSemantics
 */
final class UserAlterations
{
    /**
     * MySQL 5.6 spells the shared PASSWORD EXPIRE policy after each account; later releases write it once.
     */
    public static function write(AlterUsersStatement $statement): Tree
    {
        if (AccountForms::version($statement->origin) === 'mysql-5.6.51') {
            return new Tree('alter-users', [Build::keyword('ALTER USER'), Build::separated(array_map(static fn ($alteration): Tree => new Tree('account-expiry', [self::alteration($alteration), Build::keyword('PASSWORD EXPIRE')]), $statement->alterations))]);
        }
        return new Tree('alter-users', [
            Build::keyword('ALTER USER' . ($statement->ifExists ? ' IF EXISTS' : '')),
            Build::separated(array_map(self::alteration(...), $statement->alterations)),
            ...AccountClauses::requirement($statement->requirement),
            ...AccountClauses::limits($statement->resourceLimits),
            ...AccountClauses::policies($statement->policies),
            ...AccountClauses::annotation($statement->annotation),
        ]);
    }

    /**
     * Each change form writes its account followed by exactly its own clauses.
     */
    public static function alteration(CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval $alteration): Tree
    {
        $parts = [Accounts::write($alteration->account)];
        if ($alteration instanceof CredentialChange) {
            $parts[] = Identifications::write($alteration->identification);
            if ($alteration->replacedPassword !== null) {
                $parts[] = Build::keyword('REPLACE');
                $parts[] = Expressions::write($alteration->replacedPassword);
            }
        }
        if ($alteration instanceof AuthenticationChange) {
            $parts[] = Identifications::write($alteration->identification);
        }
        if (($alteration instanceof CredentialChange || $alteration instanceof AuthenticationChange) && $alteration->retainCurrentPassword) {
            $parts[] = Build::keyword('RETAIN CURRENT PASSWORD');
        }
        if ($alteration instanceof PluginChange) {
            $parts[] = Build::keyword('IDENTIFIED WITH');
            $parts[] = Identifications::plugin($alteration->plugin);
        }
        if ($alteration instanceof OldPasswordDiscard) {
            $parts[] = Build::keyword('DISCARD OLD PASSWORD');
        }
        if ($alteration instanceof FactorChange) {
            foreach ($alteration->factors as $factor) {
                $parts[] = self::factor($alteration->operation->value, $factor);
            }
        }
        if ($alteration instanceof FactorRemoval) {
            foreach ($alteration->factors as $factor) {
                $parts[] = new Tree('factor-removal', [Build::keyword('DROP'), Identifications::factor($factor)]);
            }
        }
        return new Tree('account-alteration', $parts);
    }

    /**
     * ADD or MODIFY, the numbered factor, and its identification.
     */
    public static function factor(string $operation, FactorIdentification $factor): Tree
    {
        return new Tree('factor-change', [Build::keyword($operation), Identifications::factor($factor->factor), Identifications::write($factor->identification)]);
    }
}
