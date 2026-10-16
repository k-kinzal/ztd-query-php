<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Checks the numeric bounds of declared types before execution can use them.
 *
 * Rule: MYSQL-DECLARED-TYPE-LIMITS-001. BIT holds 1 to 64 bits; integer display
 * widths are at most 255; fixed floating widths are 1 to 255, and FLOAT(p)
 * accepts at most 53 bits. Decimal precision is at most 65, fractional scale
 * at most 30 and no larger than precision. Temporal fractions stop at 6.
 * A display length first has to fit in an unsigned 32-bit integer, before
 * the bound of its type is checked. Failures terminate processing at the
 * declaration validation boundary: table statements retain their preceding
 * parsing notices, while a routine parameter is checked before the next
 * parameter and a local variable after its DEFAULT expression. Boundary behavior is verified against
 * MySQL 8.4.7, including decimal literals above the unsigned 64-bit range.
 * Terminates: no recursion. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeLimits
{
    /**
     * Records the first invalid bound at its position among type warnings.
     */
    public function check(TypeName $type, string $name, Derivation $derivation): void
    {
        $problem = $this->problem($type, $name, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
    }

    /**
     * Answers the first bound violation for a declaration in a release.
     * Input completion and ordinary derivation share this ordering.
     */
    public function problem(TypeName $type, string $name, \SqlSemantics\Contract\GrammarRelease $release): ?InvalidTypeSize
    {
        $problem = $this->width($type, $name) ?? ($type instanceof Floating || $type instanceof Decimal ? $this->fractional($type, $name) : $this->simple($type, $name));

        return $problem?->rule === TypeLimit::Empty && $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? null : $problem;
    }

    /**
     * Answers a display length that exceeds the common unsigned 32-bit bound.
     *
     * YEAR and temporal fractions have their own checks. A decimal length
     * is truncated to its whole digits before comparison, as BIT(1.5) is.
     */
    public function width(TypeName $type, string $name): ?InvalidTypeSize
    {
        $length = match (true) {
            $type instanceof Elementary, $type instanceof Character, $type instanceof Binary => $type->length,
            $type instanceof Integral => $type->width,
            $type instanceof Floating, $type instanceof Decimal => $type->precision,
            default => null,
        };

        return (int) $length > 4294967295 ? new InvalidTypeSize(TypeLimit::Width, $name, (int) $length, 4294967295) : null;
    }

    /**
     * Answers the invalid bound of a bit, integer or temporal type, if any.
     */
    public function simple(TypeName $type, string $name): ?InvalidTypeSize
    {
        if (($type instanceof Character && $type->kind === CharacterKind::Char) || ($type instanceof Binary && $type->kind === BinaryKind::Binary)) {
            return (int) $type->length > 255 ? new InvalidTypeSize(TypeLimit::Length, $name, (int) $type->length, 255) : null;
        }
        if ($type instanceof Elementary && $type->kind === ElementaryKind::Bit && $type->length !== null) {
            $size = (int) $type->length;

            return $size === 0 ? new InvalidTypeSize(TypeLimit::Empty, $name) : ($size > 64 ? new InvalidTypeSize(TypeLimit::Width, $name, $size, 64) : null);
        }
        if ($type instanceof Integral && $type->width !== null && (int) $type->width > 255) {
            return new InvalidTypeSize(TypeLimit::Width, $name, (int) $type->width, 255);
        }
        if ($type instanceof Temporal && in_array($type->kind, [TemporalKind::Time, TemporalKind::DateTime, TemporalKind::Timestamp], true) && (int) $type->precision > 6) {
            return new InvalidTypeSize(TypeLimit::Precision, $name, (int) $type->precision, 6);
        }

        return null;
    }

    /**
     * Answers the first invalid width, precision or scale of a fractional type.
     */
    public function fractional(Floating|Decimal $type, string $name): ?InvalidTypeSize
    {
        $precision = $type->precision === null ? 10 : (int) $type->precision;
        $scale = (int) $type->scale;
        if ($type instanceof Floating && $type->scale === null) {
            return $type->precision !== null && $precision > 53 ? new InvalidTypeSize(TypeLimit::Specifier, $name) : null;
        }
        if ($type instanceof Floating && ($precision === 0 || $precision > 255)) {
            return new InvalidTypeSize(TypeLimit::Width, $name, $precision, 255);
        }
        if ($scale > 30) {
            return new InvalidTypeSize(TypeLimit::Scale, $name, $scale, 30);
        }
        if ($type instanceof Decimal && $precision > 65) {
            return new InvalidTypeSize(TypeLimit::Precision, $name, $precision >= 2147483648 ? $precision - 4294967296 : $precision, 65);
        }

        return $precision < $scale ? new InvalidTypeSize(TypeLimit::ScaleExceedsPrecision, $name) : null;
    }
}
