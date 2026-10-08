<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
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
 * a global assignment.
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
     * @throws \MySqlMemory\Error\SqlError When the variable or the value is refused
     */
    public function assign(string $name, Scope $scope, int|float|string|null $value, ?Domain $domain): void
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
        if ($scope === Scope::Global) {
            $this->variables->globals->set($definition, $checked);
        } else {
            $this->variables->set($definition, $checked);
        }
    }

    /**
     * Checks a value for a variable and answers the value it holds.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is refused
     */
    public function check(Definition $definition, int|float|string|null $value, Domain $domain): string|int|null
    {
        $text = $value === null ? 'NULL' : (string) Convert::toText($value, $domain);
        return match ($definition->shape) {
            ValueShape::Boolean => $this->boolean($definition, $value, $domain, $text),
            ValueShape::Integer, ValueShape::Unsigned => $this->integer($definition, $value, $domain, $text),
            ValueShape::Double, ValueShape::Text => $this->text($definition, $value, $text),
        };
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
     * Checks an integer value, clipping it to the bounds of the variable.
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
        $minimum = $definition->minimum ?? PHP_INT_MIN;
        $maximum = $definition->maximum ?? PHP_INT_MAX;
        if ($number < $minimum || $number > $maximum || ($domain->unsigned && $number < 0)) {
            $this->context->warning(DataError::TruncatedWrongValue, $definition->name, $text);
            $number = $number < $minimum && !($domain->unsigned && $number < 0) ? $minimum : $maximum;
        }

        return $number;
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

            return $modes->toString();
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
