<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration as Statement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Writes reads, assignments, resets and copies without evaluating setting values. @visibility SqlSemantics

 */
final class Settings
{
    /**
     * @throws InvalidStructure
     */
    public static function write(ConfigurationStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        if ($statement instanceof Statement\ReadPragmaStatement || $statement instanceof Statement\AssignPragmaStatement) {
            return new Tree('pragma', [Build::keyword('PRAGMA'), Build::identifier($statement->name->parts, $dialect), ...($statement instanceof Statement\AssignPragmaStatement ? [Build::keyword('='), self::pragmaValue($statement->value, $dialect)] : [])]);
        }
        if ($statement instanceof Statement\ResetAllSettingsStatement) {
            return Build::keyword('RESET ALL');
        }
        if ($statement instanceof Statement\ResetAllPersistedVariablesStatement) {
            return Build::keyword('RESET PERSIST');
        }
        if ($statement instanceof Statement\ResetSettingStatement) {
            $prefix = $dialect === Dialect::MySql ? 'RESET PERSIST' : 'RESET';
            return new Tree('reset', [Build::keyword($prefix), ...($statement->setting->ifExists ? [Build::keyword('IF EXISTS')] : []), Build::identifier($statement->setting->name, $dialect)]);
        }
        if (!$statement instanceof Statement\SetStatement) {
            throw new InvalidStructure('Unclassified configuration statement.');
        }
        return new Tree('set', [Build::keyword('SET'), Build::separated(array_map(static fn ($setting): Tree => self::assignment($setting, $dialect), $statement->settings))]);
    }

    /**
     * Serializes an assignment value in the setting's applicable SQL form.
     */
    public static function assignment(Configuration\DefaultSetting|Configuration\AssignedUserVariable|Configuration\AssignedSetting|Configuration\CurrentSetting $setting, Dialect $dialect): Tree
    {
        if ($setting instanceof Configuration\AssignedUserVariable) {
            return new Tree('user-assignment', [Scalar\ReferenceExpressions::write($setting->target), Build::keyword('='), Expressions::write($setting->value)]);
        }
        $scope = $setting->scope;
        $name = $setting->name;
        $prefix = $scope === Configuration\SettingScope::Session ? [] : [Build::keyword(strtoupper(str_replace('-', '_', $scope->value)))];
        if ($scope === Configuration\SettingScope::User) {
            $target = Scalar\ReferenceExpressions::variable(implode('.', $name), \SqlSemantics\Schema\VariableScope::User, $dialect);
            $prefix = [];
        } else {
            $target = Build::identifier($name, $dialect);
        }
        if ($setting instanceof Configuration\DefaultSetting) {
            return new Tree('setting', [...$prefix, $target, Build::keyword('= DEFAULT')]);
        }
        if ($setting instanceof Configuration\CurrentSetting) {
            return new Tree('setting', [...$prefix, $target, Build::keyword('FROM CURRENT')]);
        }
        return new Tree('setting', [...$prefix, $target, Build::keyword('='), Build::separated(array_map(Expressions::write(...), $setting->values))]);
    }
    /**
     * Writes a pragma's scalar value without introducing expression parentheses.
     * @throws InvalidStructure
     */
    public static function pragmaValue(Configuration\Pragma\Argument $value, Dialect $dialect): Tree
    {
        return match (true) {
            $value instanceof Configuration\Pragma\IdentifierArgument => Build::identifier([$value->name], $dialect),
            $value instanceof Configuration\Pragma\TextArgument => Expressions::write($value->literal),
            $value instanceof Configuration\Pragma\NumericArgument => new Tree('pragma-number', [Build::keyword($value->sign->value), Expressions::write($value->literal)]),
            default => throw new InvalidStructure('Unclassified pragma argument.'),
        };
    }

}
