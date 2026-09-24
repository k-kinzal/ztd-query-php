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
        $account = Session\AccountSettings::write($statement);
        if ($account !== null) {
            return $account;
        }
        if ($statement instanceof Statement\Transaction\SetNextTransactionStatement || $statement instanceof Statement\Transaction\SetDefaultTransactionStatement || $statement instanceof Statement\Transaction\SetCurrentTransactionStatement || $statement instanceof Statement\Transaction\SetSessionTransactionStatement || $statement instanceof Statement\Transaction\SetTransactionSnapshotStatement) {
            return Session\TransactionSettings::write($statement);
        }
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
        $items = [];
        $carried = Configuration\SettingScope::Session;
        foreach ($statement->settings as $setting) {
            $items[] = self::assignment($setting, $dialect, $carried);
            if ($dialect === Dialect::MySql && ($setting instanceof Configuration\AssignedSetting || $setting instanceof Configuration\DefaultSetting)) {
                $carried = $setting->scope;
            }
        }
        return new Tree('set', [Build::keyword('SET'), Build::separated($items)]);
    }

    /**
     * Serializes an assignment value in the setting's applicable SQL form; a MySQL session item after a scoped item spells SESSION, because the server carries the earlier scope over.
     */
    public static function assignment(Configuration\DefaultSetting|Configuration\AssignedUserVariable|Configuration\AssignedSetting|Configuration\CurrentSetting|Configuration\Connection\ConnectionNames|Configuration\Connection\ConnectionCharacterSet $setting, Dialect $dialect, Configuration\SettingScope $carried = Configuration\SettingScope::Session): Tree
    {
        if ($setting instanceof Configuration\Connection\ConnectionNames || $setting instanceof Configuration\Connection\ConnectionCharacterSet) {
            return Session\ConnectionCharsets::write($setting);
        }
        if ($setting instanceof Configuration\AssignedUserVariable) {
            return new Tree('user-assignment', [Scalar\ReferenceExpressions::write($setting->target), Build::keyword('='), Expressions::write($setting->value)]);
        }
        $scope = $setting->scope;
        $name = $setting->name;
        $prefix = $scope === Configuration\SettingScope::Session && $carried === Configuration\SettingScope::Session ? [] : [Build::keyword(strtoupper(str_replace('-', '_', $scope->value)))];
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
        $zone = $dialect === Dialect::PostgreSql && $name === ['timezone'] && count($setting->values) === 1 ? self::intervalZone($setting->values[0]) : null;
        if ($zone !== null) {
            return new Tree('setting', [...$prefix, Build::keyword('TIME ZONE'), $zone]);
        }
        return new Tree('setting', [...$prefix, $target, Build::keyword('='), Build::separated(array_map(Expressions::write(...), $setting->values))]);
    }

    /**
     * Writes a PostgreSQL time zone given as an interval in the `INTERVAL 'text' fields` or `INTERVAL(p) 'text'` form that SET TIME ZONE accepts, or returns null for any other value.
     */
    public static function intervalZone(\SqlSemantics\Model\Expression $value): ?Tree
    {
        $interval = $value->type->identity;
        if (!$value instanceof \SqlSemantics\Model\Scalar\Operator\CastExpression || !$value->operand instanceof \SqlSemantics\Model\Scalar\Value\Literal || !$interval instanceof \SqlSemantics\Type\Identity\IntervalStorage) {
            return null;
        }
        $precision = Type\TypeParameters::numbers([$interval->precision]);
        return $interval->fields === \SqlSemantics\Type\Identity\IntervalFields::All
            ? new Tree('interval-zone', [Build::keyword('INTERVAL'), $precision, Expressions::write($value->operand)])
            : new Tree('interval-zone', [Build::keyword('INTERVAL'), Expressions::write($value->operand), Build::keyword($interval->fields->value), $precision]);
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
