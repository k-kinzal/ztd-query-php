<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Raises the warnings of the deprecated parts of a data type, as the server raises them when it reads the type.
 *
 * Rule: MYSQL-TYPE-NOTICE-001. A display width of an integer type other than
 * TINYINT(1) is deprecated, so is ZEROFILL, the digits of FLOAT and DOUBLE,
 * UNSIGNED on DECIMAL, FLOAT and DOUBLE, YEAR(4), and the character set name
 * utf8; each raises its warning (MYSQL-DEPRECATION-001) in that order.
 * Terminates: no recursion. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeNotices
{
    /**
     * Raises the warnings of a data type.
     */
    public function type(TypeName $type, Derivation $derivation): void
    {
        $modifiers = $type instanceof Integral || $type instanceof Decimal || $type instanceof Floating ? $type->modifiers : [];
        if ($type instanceof Integral && $type->width !== null && !($type->kind === IntegralKind::TinyInt && $type->width === '1') && !in_array(NumericModifier::Zerofill, $modifiers, true)) {
            Deprecation::raise(Deprecated::DisplayWidth, $derivation);
        }
        if (in_array(NumericModifier::Zerofill, $modifiers, true)) {
            Deprecation::raise(Deprecated::Zerofill, $derivation);
        }
        if ($type instanceof Floating && $type->scale !== null) {
            Deprecation::raise(Deprecated::FloatingDigits, $derivation);
        }
        if (($type instanceof Decimal || $type instanceof Floating) && in_array(NumericModifier::Unsigned, $modifiers, true)) {
            Deprecation::raise(Deprecated::UnsignedFraction, $derivation);
        }
        if ($type instanceof Temporal && $type->kind === TemporalKind::Year && $type->precision === '4') {
            Deprecation::raise(Deprecated::YearWidth, $derivation);
        }
        if ($type instanceof Character || $type instanceof Enumeration) {
            $this->charset($type->charset, $derivation);
        }
    }

    /**
     * Raises the warning of the character set name utf8.
     */
    public function charset(?CharsetAttribute $charset, Derivation $derivation): void
    {
        if ($charset !== null && $charset->charset !== null && strtolower($charset->charset->value) === 'utf8') {
            Deprecation::raise(Deprecated::Utf8Alias, $derivation);
        }
    }
}
