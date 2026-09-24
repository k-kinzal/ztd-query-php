<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Account;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes IDENTIFIED clauses from their typed credential forms, keeping every literal spelling.
 * @visibility SqlSemantics
 */
final class Identifications
{
    /**
     * Each credential form has exactly one keyword spelling.
     */
    public static function write(PasswordIdentification|HashIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification $identification): Tree
    {
        return new Tree('identification', match (true) {
            $identification instanceof PasswordIdentification => [Build::keyword('IDENTIFIED BY'), Expressions::write($identification->password)],
            $identification instanceof HashIdentification => [Build::keyword('IDENTIFIED BY PASSWORD'), Expressions::write($identification->hash)],
            $identification === RandomPassword::Generated => [Build::keyword('IDENTIFIED BY RANDOM PASSWORD')],
            $identification instanceof PluginIdentification => [Build::keyword('IDENTIFIED WITH'), self::plugin($identification->plugin)],
            $identification instanceof PluginHashIdentification => [Build::keyword('IDENTIFIED WITH'), self::plugin($identification->plugin), Build::keyword('AS'), Expressions::write($identification->hash)],
            $identification instanceof PluginPasswordIdentification => [Build::keyword('IDENTIFIED WITH'), self::plugin($identification->plugin), Build::keyword('BY'), Expressions::write($identification->password)],
            $identification instanceof PluginRandomPasswordIdentification => [Build::keyword('IDENTIFIED WITH'), self::plugin($identification->plugin), Build::keyword('BY RANDOM PASSWORD')],
        });
    }

    /**
     * A plugin name is written as a quoted identifier.
     */
    public static function plugin(string $plugin): Tree
    {
        return Build::identifier([$plugin], Dialect::MySql);
    }

    /**
     * A numbered factor is written as its number followed by FACTOR.
     */
    public static function factor(AuthenticationFactor $factor): Tree
    {
        return Build::keyword($factor->value . ' FACTOR');
    }
}
