<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Function\Digest\Ciphers;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

/**
 * Checks and assigns a value to a system variable, as SET does.
 *
 * A read-only variable is refused (ER_INCORRECT_GLOBAL_LOCAL_VAR); a global-only one set for
 * the session and a session-only one set globally are refused. A boolean takes ON, OFF, 1 and
 * 0; an integer is clipped to its bounds with a warning; NULL is taken by character_set_results,
 * session_track_system_variables, innodb_tmpdir and innodb_ft_user_stopword_table only, and
 * refused by any other variable; DEFAULT restores the global value, or the compiled default for
 * a global assignment. block_encryption_mode takes the name of an AES mode, in any case, or its
 * number, and holds the name in lower case. A variable MySQL 5.6 or 5.7 deprecates warns as its
 * assignment is checked (see DeprecatedVariables).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility MySqlMemory
 */
final class Assigner
{
    /**
     * @param Variables $variables The variables of the session
     * @param Context $context The statement
     */
    public function __construct(public readonly Variables $variables, public readonly Context $context)
    {
    }

    /**
     * Assigns a value, or DEFAULT when the value is null and the domain is null.
     *
     * @param string|null $cache The key cache whose parameter is assigned, or null for the variable
     * @throws \MySqlMemory\Error\SqlError When the variable or the value is refused
     */
    public function assign(string $name, Scope $scope, int|float|string|null $value, ?Domain $domain, ?string $cache = null): void
    {
        $definition = $this->variables->catalog->find($name);
        if ($definition === null) {
            throw AdministrationError::UnknownSystemVariable->error($name);
        }
        if ($definition->writability === Writability::ReadOnly) {
            throw AdministrationError::IncorrectGlobalLocalVariable->error($definition->name, 'read only');
        }
        if ($scope === Scope::Session && !$definition->reach->session()) {
            throw AdministrationError::GlobalVariable->error($definition->name);
        }
        if ($scope === Scope::Global && !$definition->reach->global()) {
            throw AdministrationError::LocalVariable->error($definition->name);
        }
        if ($scope === Scope::Session && $definition->writability === Writability::GlobalOnly) {
            throw AdministrationError::VariableIsReadonly->error('SESSION', $definition->name, 'GLOBAL');
        }
        $checked = $domain === null ? ($scope === Scope::Global ? $definition->default : $this->variables->globals->value($definition)) : $this->check($definition, $value, $domain);
        if ($cache !== null) {
            $this->cache($cache, $definition, $checked);

            return;
        }
        if ($definition->name === 'timestamp' && ($domain === null || $checked === null)) {
            unset($this->variables->session['timestamp']);

            return;
        }
        foreach ($this->aliases($definition) as $alias) {
            if ($scope === Scope::Global) {
                $this->variables->globals->set($alias, $checked);
            } else {
                $this->variables->set($alias, $checked);
            }
        }
    }

    /**
     * Sets a parameter of a key cache, once its value is checked; MySQL 8.0 and later then warn about the syntax as deprecated (ER_WARN_DEPRECATED_SYNTAX_NO_REPLACEMENT; verified on live 5.6.51, 5.7.44 and 8.4.7 servers).
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/structured-system-variables.html.
     */
    public function cache(string $cache, Definition $definition, string|int|null $value): void
    {
        $this->variables->globals->cache($cache, $definition, $value);
        if ($this->context->modes->release !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && $this->context->modes->release !== \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $this->context->diagnostics->warning(1287, $cache . '.' . $definition->name . ' syntax is deprecated and will be removed in a future release');
        }
    }

    /**
     * Records the warning MySQL 5.6 and 5.7 raise as they check the assignment of a variable they deprecate (see DeprecatedVariables), before the value is checked (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function retired(string $name): void
    {
        $definition = $this->variables->catalog->find($name);
        $deprecated = $definition === null ? null : (new \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\DeprecatedVariables())->warning($definition->name, $this->context->modes->release);
        if ($deprecated !== null) {
            $this->context->diagnostics->warning(1287, $deprecated);
        }
    }

    /**
     * Answers a variable with the variables that are other names of it: transaction_isolation and tx_isolation, transaction_read_only and tx_read_only, which MySQL 5.7 has both of.
     *
     * Source: https://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_tx_isolation.
     *
     * @return list<Definition>
     */
    public function aliases(Definition $definition): array
    {
        $names = ['transaction_isolation' => 'tx_isolation', 'tx_isolation' => 'transaction_isolation', 'transaction_read_only' => 'tx_read_only', 'tx_read_only' => 'transaction_read_only'];
        $alias = isset($names[$definition->name]) ? $this->variables->catalog->find($names[$definition->name]) : null;

        return $alias === null ? [$definition] : [$definition, $alias];
    }

    /**
     * Checks a value for a variable and answers the value it holds.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is refused
     */
    public function check(Definition $definition, int|float|string|null $value, Domain $domain): string|int|null
    {
        $text = $value === null ? 'NULL' : (string) Convert::toText($value, $domain);
        $clock = new TimeSettings($this->context);
        if ($definition->name === 'time_zone') {
            return $clock->zone($value, $domain);
        }
        if ($definition->name === 'lc_time_names') {
            return $clock->locale($value, $domain);
        }
        if ($definition->name === 'timestamp') {
            return $clock->timestamp($value, $domain);
        }
        if ($definition->name === 'transaction_isolation' || $definition->name === 'tx_isolation') {
            return $this->isolation($definition, $value, $domain, $text);
        }
        if ($definition->name === 'sql_mode' && $value !== null && in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Double], true) && in_array($this->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            return $this->modes($definition, $value, $domain, $text);
        }

        return match ($definition->shape) {
            ValueShape::Boolean => $this->boolean($definition, $value, $domain, $text),
            ValueShape::Integer, ValueShape::Unsigned => $this->integer($definition, $value, $domain, $text),
            ValueShape::Double, ValueShape::Text => $this->text($definition, $value, $text),
        };
    }

    /**
     * Checks an isolation level: its name in any letter case, or its number from 0 to 3; held as the name in upper case.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_transaction_isolation.
     *
     * @throws \MySqlMemory\Error\SqlError When the value names no isolation level
     */
    public function isolation(Definition $definition, int|float|string|null $value, Domain $domain, string $text): string
    {
        if ($value !== null && ($domain->kind === Kind::Decimal || $domain->kind === Kind::Double)) {
            throw AdministrationError::WrongTypeForVariable->error($definition->name);
        }
        $level = $value === null ? null : \MySqlMemory\Concurrency\Isolation::named($text);

        return $level->value ?? throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
    }

    /**
     * Checks a boolean value.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is not a boolean
     */
    public function boolean(Definition $definition, int|float|string|null $value, Domain $domain, string $text): string
    {
        if ($value !== null && ($domain->kind === Kind::Integer || $domain->kind === Kind::Decimal) && in_array($text, ['0', '1'], true)) {
            return $text === '1' ? 'ON' : 'OFF';
        }
        if ($value !== null && $domain->kind === Kind::Integer || $domain->kind === Kind::Decimal || $domain->kind === Kind::Double) {
            if ($domain->kind !== Kind::Integer) {
                throw AdministrationError::WrongTypeForVariable->error($definition->name);
            }
            throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
        }
        $word = strtoupper($text);
        if (in_array($word, ['ON', 'TRUE', '1'], true)) {
            return 'ON';
        }
        if (in_array($word, ['OFF', 'FALSE', '0'], true)) {
            return 'OFF';
        }

        throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
    }

    /**
     * Checks an integer value, clipping it to the bounds of the variable; the value and the bounds of an unsigned variable compare as unsigned 64-bit integers, and an unsigned variable without known bounds (the catalogs of MySQL 5.6 and 5.7 have none) takes any unsigned 64-bit integer.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is NULL or not an integer
     */
    public function integer(Definition $definition, int|float|string|null $value, Domain $domain, string $text): int
    {
        if ($value === null) {
            throw AdministrationError::WrongValueForVariable->error($definition->name, 'NULL');
        }
        if ($domain->kind !== Kind::Integer && $domain->kind !== Kind::Year) {
            throw AdministrationError::WrongTypeForVariable->error($definition->name);
        }
        $number = (int) $value;
        $unsigned = $definition->shape === ValueShape::Unsigned;
        $minimum = $definition->minimum ?? ($unsigned ? 0 : PHP_INT_MIN);
        $maximum = $definition->maximum ?? ($unsigned ? -1 : PHP_INT_MAX);
        if ($unsigned) {
            $below = (!$domain->unsigned && $number < 0) || \MySqlMemory\Value\Integer::compare($number, true, $minimum, true) < 0;
            $above = !$below && \MySqlMemory\Value\Integer::compare($number, true, $maximum, true) > 0;
        } else {
            $below = $number < $minimum && !($domain->unsigned && $number < 0);
            $above = $number > $maximum || ($domain->unsigned && $number < 0);
        }
        if ($below || $above) {
            $this->context->warning(DataError::TruncatedWrongValue, $definition->name, $text);
            $number = $below ? $minimum : $maximum;
        }

        return $number;
    }

    /**
     * Checks a number assigned to sql_mode in MySQL 5.6 and 5.7: the modes whose bits it sets, each mode the bit of its place in the list of the release; a number that is not an integer is ER_WRONG_TYPE_FOR_VAR, a negative one or one beyond 32 bits ER_WRONG_VALUE_FOR_VAR (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws \MySqlMemory\Error\SqlError When the number does not stand for modes
     */
    public function modes(Definition $definition, int|float|string $value, Domain $domain, string $text): string
    {
        if ($domain->kind !== Kind::Integer) {
            throw AdministrationError::WrongTypeForVariable->error($definition->name);
        }
        $number = (int) $value;
        if ($number < 0 || $number > 4294967295 || $text !== (string) $number) {
            throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
        }
        $names = [];
        foreach (SqlModes::names($this->context->modes->release) as $bit => $name) {
            if ((($number >> $bit) & 1) === 1 && !str_starts_with($name, 'NOT_USED')) {
                $names[] = $name;
            }
        }
        $modes = new SqlModes($names, $this->context->modes->release);
        $this->deprecated($modes);

        return $modes->toString();
    }

    /**
     * Records the warnings MySQL 5.7 raises for a new sql_mode: that NO_ZERO_DATE, NO_ZERO_IN_DATE and ERROR_FOR_DIVISION_BY_ZERO belong with a strict mode, unless the modes hold all of them with a strict mode or none of them, then that changing NO_AUTO_CREATE_USER from the mode of the session is deprecated (verified on a live 5.7.44 server).
     */
    public function deprecated(SqlModes $modes): void
    {
        if ($modes->release !== \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return;
        }
        $held = count(array_filter(['NO_ZERO_DATE', 'NO_ZERO_IN_DATE', 'ERROR_FOR_DIVISION_BY_ZERO'], $modes->has(...)));
        $strict = $modes->has('STRICT_TRANS_TABLES') || $modes->has('STRICT_ALL_TABLES');
        if (!($strict && $held === 3) && !(!$strict && $held === 0)) {
            $this->context->diagnostics->warning(3135, "'NO_ZERO_DATE', 'NO_ZERO_IN_DATE' and 'ERROR_FOR_DIVISION_BY_ZERO' sql modes should be used with strict mode. They will be merged with strict mode in a future release.");
        }
        if ($modes->has('NO_AUTO_CREATE_USER') !== $this->context->modes->has('NO_AUTO_CREATE_USER')) {
            $this->context->diagnostics->warning(3090, "Changing sql mode 'NO_AUTO_CREATE_USER' is deprecated. It will be removed in a future release.");
        }
    }

    /**
     * Checks a text value; sql_mode and the collation and character set variables are checked by name.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is NULL for a variable that does not take it, or not a mode, collation or character set the variable takes
     */
    public function text(Definition $definition, int|float|string|null $value, string $text): ?string
    {
        if ($value === null) {
            if (!in_array($definition->name, ['character_set_results', 'session_track_system_variables', 'innodb_tmpdir', 'innodb_ft_user_stopword_table'], true)) {
                throw AdministrationError::WrongValueForVariable->error($definition->name, 'NULL');
            }

            return null;
        }
        if ($definition->name === 'sql_mode') {
            $modes = SqlModes::parse($text, $this->context->modes->release);
            if ($modes === null) {
                throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
            }
            $this->deprecated($modes);

            return $modes->toString();
        }
        if ($definition->name === 'block_encryption_mode') {
            return Ciphers::mode($text) ?? throw AdministrationError::WrongValueForVariable->error($definition->name, $text);
        }
        if (str_starts_with($definition->name, 'collation_')) {
            $collation = Collation::named($text);
            if ($collation === null) {
                throw SchemaError::UnknownCollation->error($text);
            }

            return $collation->name;
        }
        if (str_starts_with($definition->name, 'character_set_')) {
            $charset = Charset::named($text);
            if ($charset === null) {
                throw SchemaError::UnknownCharacterSet->error($text);
            }

            return $charset->name;
        }

        return $text;
    }
}
