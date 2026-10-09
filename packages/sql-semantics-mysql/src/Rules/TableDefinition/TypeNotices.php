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
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
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
        if ($type instanceof Integral || $type instanceof Decimal || $type instanceof Floating) {
            $this->numeric($type, $derivation);
        }
        if ($type instanceof Temporal && $type->kind === TemporalKind::Year && $type->precision === '4') {
            Deprecation::raise(Deprecated::YearWidth, $derivation);
        }
        if ($type instanceof Character || $type instanceof Enumeration) {
            $this->charset($type->charset, $derivation);
        }
        if ($type instanceof Character && $type->national) {
            Deprecation::raise(Deprecated::National, $derivation);
        }
    }

    /**
     * Raises the warnings of a numeric type: its display width, ZEROFILL, the digits of FLOAT and DOUBLE, and UNSIGNED on a type with a fraction.
     */
    public function numeric(Integral|Decimal|Floating $type, Derivation $derivation): void
    {
        $modifiers = $type->modifiers;
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
    }

    /**
     * Raises the warnings of a deprecated character set name or shorthand.
     */
    public function charset(?CharsetAttribute $charset, Derivation $derivation): void
    {
        if ($charset?->form === CharsetForm::Binary || $charset?->mark === BinaryMark::Leading) {
            Deprecation::raise(Deprecated::BinaryAttribute, $derivation);
        }
        if ($charset?->charset !== null) {
            Deprecation::charset($charset->charset->value, $derivation);
        }
        if ($charset?->form === CharsetForm::Ascii) {
            Deprecation::raise(Deprecated::AsciiCharset, $derivation);
        }
        if ($charset?->form === CharsetForm::Unicode) {
            Deprecation::raise(Deprecated::UnicodeCharset, $derivation);
        }
        if ($charset?->mark === BinaryMark::Trailing) {
            Deprecation::raise(Deprecated::BinaryAttribute, $derivation);
        }
    }
}
