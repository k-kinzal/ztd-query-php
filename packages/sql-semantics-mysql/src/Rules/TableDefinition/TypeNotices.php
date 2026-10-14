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
            Deprecation::raise(Deprecated::YearWidth, $derivation, $type);
        }
        if ($type instanceof Character || $type instanceof Enumeration) {
            $this->charset($type->charset, $derivation, $type);
        }
        if ($type instanceof Character && $type->national) {
            Deprecation::raise(Deprecated::National, $derivation, $type);
        }
    }

    /**
     * Raises the warnings of a numeric type: its display width, ZEROFILL, the digits of FLOAT and DOUBLE, and UNSIGNED on a type with a fraction.
     */
    public function numeric(Integral|Decimal|Floating $type, Derivation $derivation): void
    {
        foreach ($this->numericWarnings($type) as $notice) {
            Deprecation::raise($notice, $derivation, $type);
        }
    }

    /**
     * Lists numeric-type notices before applying a release profile.
     *
     * @return list<Deprecated>
     */
    public function numericWarnings(Integral|Decimal|Floating $type): array
    {
        $notices = [];
        $modifiers = $type->modifiers;
        if ($type instanceof Integral && $type->width !== null && !($type->kind === IntegralKind::TinyInt && $type->width === '1') && !in_array(NumericModifier::Zerofill, $modifiers, true)) {
            $notices[] = Deprecated::DisplayWidth;
        }
        if (in_array(NumericModifier::Zerofill, $modifiers, true)) {
            $notices[] = Deprecated::Zerofill;
        }
        if ($type instanceof Floating && $type->scale !== null) {
            $notices[] = Deprecated::FloatingDigits;
        }
        if (($type instanceof Decimal || $type instanceof Floating) && in_array(NumericModifier::Unsigned, $modifiers, true)) {
            $notices[] = Deprecated::UnsignedFraction;
        }

        return $notices;
    }

    /**
     * Raises the warnings of a deprecated character set name or shorthand.
     */
    public function charset(?CharsetAttribute $charset, Derivation $derivation, ?\SqlSemantics\Statement\Node $at = null): void
    {
        $name = $charset?->charset;
        $problem = $name !== null && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($name->value) === null ? new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($name->value) : null;
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $at);
            return;
        }
        foreach ($this->charsetWarnings($charset) as $notice) {
            Deprecation::raise($notice, $derivation, $at);
        }
    }

    /**
     * Lists the shorthand notices in operand order, before applying a release profile.
     *
     * @return list<Deprecated> The ordered warnings of the typed attribute
     */
    public function charsetWarnings(?CharsetAttribute $charset): array
    {
        $notices = [];
        if ($charset?->form === CharsetForm::Binary || $charset?->mark === BinaryMark::Leading) {
            $notices[] = Deprecated::BinaryAttribute;
        }
        $named = match (strtolower($charset?->charset->value ?? '')) {
            'utf8' => Deprecated::Utf8Alias,
            'utf8mb3' => Deprecated::Utf8mb3,
            'ucs2' => Deprecated::Ucs2,
            default => null,
        };
        if ($named !== null) {
            $notices[] = $named;
        }
        if ($charset?->form === CharsetForm::Ascii) {
            $notices[] = Deprecated::AsciiCharset;
        }
        if ($charset?->form === CharsetForm::Unicode) {
            $notices[] = Deprecated::UnicodeCharset;
        }
        if ($charset?->mark === BinaryMark::Trailing) {
            $notices[] = Deprecated::BinaryAttribute;
        }

        return $notices;
    }
}
