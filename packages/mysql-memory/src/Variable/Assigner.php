<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\ErrorCode;
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
 * 0; an integer is clipped to its bounds with a warning; DEFAULT restores the global value, or
 * the compiled default for a global assignment.
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
            throw ErrorCode::UnknownSystemVariable->error($name);
        }
        if ($definition->writability === Writability::ReadOnly) {
            throw ErrorCode::IncorrectGlobalLocalVariable->error($definition->name, 'read only');
        }
        if ($scope === Scope::Session && !$definition->reach->session()) {
            throw ErrorCode::GlobalVariable->error($definition->name);
        }
        if ($scope === Scope::Global && !$definition->reach->global()) {
            throw ErrorCode::LocalVariable->error($definition->name);
        }
        if ($scope === Scope::Session && $definition->writability === Writability::GlobalOnly) {
            throw ErrorCode::VariableIsReadonly->error('SESSION', $definition->name, 'GLOBAL');
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
    public function check(Definition $definition, int|float|string|null $value, Domain $domain): string|int
    {
        $text = $value === null ? 'NULL' : (string) Convert::toText($value, $domain);
        return match ($definition->shape) {
            ValueShape::Boolean => $this->boolean($definition, $value, $domain, $text),
            ValueShape::Integer, ValueShape::Unsigned => $this->integer($definition, $value, $domain, $text),
            default => $this->text($definition, $value, $text),
        };
    }

    /**
     * Checks a boolean value.
     */
    public function boolean(Definition $definition, int|float|string|null $value, Domain $domain, string $text): string
    {
        if ($value !== null && ($domain->kind === Kind::Integer || $domain->kind === Kind::Decimal) && in_array($text, ['0', '1'], true)) {
            return $text === '1' ? 'ON' : 'OFF';
        }
        if ($value !== null && $domain->kind === Kind::Integer || $domain->kind === Kind::Decimal || $domain->kind === Kind::Double) {
            if ($domain->kind !== Kind::Integer) {
                throw ErrorCode::WrongTypeForVariable->error($definition->name);
            }
            throw ErrorCode::WrongValueForVariable->error($definition->name, $text);
        }
        $word = strtoupper($text);
        if (in_array($word, ['ON', 'TRUE', '1'], true)) {
            return 'ON';
        }
        if (in_array($word, ['OFF', 'FALSE', '0'], true)) {
            return 'OFF';
        }

        throw ErrorCode::WrongValueForVariable->error($definition->name, $text);
    }

    /**
     * Checks an integer value, clipping it to the bounds of the variable.
     */
    public function integer(Definition $definition, int|float|string|null $value, Domain $domain, string $text): int
    {
        if ($value === null) {
            throw ErrorCode::WrongValueForVariable->error($definition->name, 'NULL');
        }
        if ($domain->kind !== Kind::Integer && $domain->kind !== Kind::Year) {
            throw ErrorCode::WrongTypeForVariable->error($definition->name);
        }
        $number = (int) $value;
        $minimum = $definition->minimum ?? PHP_INT_MIN;
        $maximum = $definition->maximum ?? PHP_INT_MAX;
        if ($number < $minimum || $number > $maximum || ($domain->unsigned && $number < 0)) {
            $this->context->warning(ErrorCode::TruncatedWrongValue, $definition->name, $text);
            $number = $number < $minimum && !($domain->unsigned && $number < 0) ? $minimum : $maximum;
        }

        return $number;
    }

    /**
     * Checks a text value; sql_mode and the collation and character set variables are checked by name.
     */
    public function text(Definition $definition, int|float|string|null $value, string $text): string
    {
        if ($value === null && !in_array($definition->name, ['character_set_client', 'character_set_results', 'character_set_connection'], true)) {
            throw ErrorCode::WrongValueForVariable->error($definition->name, 'NULL');
        }
        if ($definition->name === 'sql_mode') {
            $modes = SqlModes::parse($text);
            if ($modes === null) {
                throw ErrorCode::WrongValueForVariable->error($definition->name, $text);
            }

            return $modes->toString();
        }
        if (str_starts_with($definition->name, 'collation_')) {
            $collation = Collation::named($text);
            if ($collation === null) {
                throw ErrorCode::UnknownCollation->error($text);
            }

            return $collation->name;
        }
        if (str_starts_with($definition->name, 'character_set_')) {
            $charset = Charset::named($text);
            if ($charset === null) {
                throw ErrorCode::UnknownCharacterSet->error($text);
            }

            return $charset->name;
        }

        return $text;
    }
}
