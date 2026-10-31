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
     *
     * @param \SqlSemantics\Statement\Node|null $at The completed declaration, or the current derivation occurrence
     */
    public function check(TypeName $type, string $name, Derivation $derivation, ?\SqlSemantics\Statement\Node $at = null): void
    {
        $problem = $this->problem($type, $name, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
            $contextual = $at instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition && !in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            $derivation->warn(new ParseFailure($problem, true, $contextual ? \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization : \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax), $at);
        }
    }

    /**
     * Answers the first bound violation for a declaration in a release.
     * Input completion and ordinary derivation share this ordering.
     */
    public function problem(TypeName $type, string $name, \SqlSemantics\Contract\GrammarRelease $release): ?InvalidTypeSize
    {
        $problem = $type instanceof Floating || $type instanceof Decimal ? $this->fractional($type, $name) : $this->simple($type, $name);

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

        return $this->overflow($length, $name);
    }

    /**
     * Answers the display length, precision or width that exceeds the unsigned 32-bit bound, if any.
     */
    public function overflow(?string $length, string $name): ?InvalidTypeSize
    {
        return (int) $length > 4294967295 ? new InvalidTypeSize(TypeLimit::Width, $name, (int) $length, 4294967295) : null;
    }

    /**
     * Answers the invalid bound of a bit, integer or temporal type, if any.
     */
    public function simple(TypeName $type, string $name): ?InvalidTypeSize
    {
        return match (true) {
            $type instanceof Character => $this->characterLength($type->kind, $type->length, $name),
            $type instanceof Binary => $this->binaryLength($type->kind, $type->length, $name),
            $type instanceof Elementary => $this->elementaryLength($type->kind, $type->length, $name),
            $type instanceof Integral => $this->integralWidth($type->width, $name),
            $type instanceof Temporal => $this->temporalPrecision($type->kind, $type->precision, $name),
            default => null,
        };
    }

    /**
     * Answers the bound a CHAR length exceeds: 255 characters (ER_TOO_BIG_FIELDLENGTH).
     */
    public function characterLength(CharacterKind $kind, ?string $length, string $name): ?InvalidTypeSize
    {
        return $this->overflow($length, $name) ?? ($kind === CharacterKind::Char && (int) $length > 255 ? new InvalidTypeSize(TypeLimit::Length, $name, (int) $length, 255) : null);
    }

    /**
     * Answers the bound a BINARY length exceeds: 255 bytes (ER_TOO_BIG_FIELDLENGTH).
     */
    public function binaryLength(BinaryKind $kind, ?string $length, string $name): ?InvalidTypeSize
    {
        return $this->overflow($length, $name) ?? ($kind === BinaryKind::Binary && (int) $length > 255 ? new InvalidTypeSize(TypeLimit::Length, $name, (int) $length, 255) : null);
    }

    /**
     * Answers the bound a BIT width exceeds: an empty width (which 5.6 accepts, so the caller's release decides) or more than 64 bits.
     */
    public function elementaryLength(ElementaryKind $kind, ?string $length, string $name): ?InvalidTypeSize
    {
        $overflow = $this->overflow($length, $name);
        if ($overflow !== null || $kind !== ElementaryKind::Bit || $length === null) {
            return $overflow;
        }
        $size = (int) $length;

        return $size === 0 ? new InvalidTypeSize(TypeLimit::Empty, $name) : ($size > 64 ? new InvalidTypeSize(TypeLimit::Width, $name, $size, 64) : null);
    }

    /**
     * Answers the bound an integer display width exceeds: 255 (ER_TOO_BIG_DISPLAYWIDTH).
     */
    public function integralWidth(?string $width, string $name): ?InvalidTypeSize
    {
        return $this->overflow($width, $name) ?? ($width !== null && (int) $width > 255 ? new InvalidTypeSize(TypeLimit::Width, $name, (int) $width, 255) : null);
    }

    /**
     * Answers the bound a fractional-second precision exceeds: 6 (ER_TOO_BIG_PRECISION).
     */
    public function temporalPrecision(TemporalKind $kind, ?string $precision, string $name): ?InvalidTypeSize
    {
        return in_array($kind, [TemporalKind::Time, TemporalKind::DateTime, TemporalKind::Timestamp], true) && (int) $precision > 6 ? new InvalidTypeSize(TypeLimit::Precision, $name, (int) $precision, 6) : null;
    }

    /**
     * Answers the first invalid width, precision or scale of a fractional type.
     */
    public function fractional(Floating|Decimal $type, string $name): ?InvalidTypeSize
    {
        return $this->fractionalParts($type instanceof Decimal, $type->precision, $type->scale, $name);
    }

    /**
     * Answers the first invalid width, precision or scale of DECIMAL or a floating-point type written as parts.
     */
    public function fractionalParts(bool $decimal, ?string $precision, ?string $scale, string $name): ?InvalidTypeSize
    {
        $overflow = $this->overflow($precision, $name);
        if ($overflow !== null) {
            return $overflow;
        }
        $digits = $precision === null ? 10 : (int) $precision;
        $fraction = (int) $scale;
        if (!$decimal && $scale === null) {
            return $precision !== null && $digits > 53 ? new InvalidTypeSize(TypeLimit::Specifier, $name) : null;
        }
        if ($fraction > 30) {
            return new InvalidTypeSize(TypeLimit::Scale, $name, $fraction, 30);
        }
        if (!$decimal && ($digits === 0 || $digits > 255)) {
            return new InvalidTypeSize(TypeLimit::Width, $name, $digits, 255);
        }
        if ($decimal && $digits > 65) {
            return new InvalidTypeSize(TypeLimit::Precision, $name, $digits >= 2147483648 ? $digits - 4294967296 : $digits, 65);
        }

        return $digits < $fraction ? new InvalidTypeSize(TypeLimit::ScaleExceedsPrecision, $name) : null;
    }

    /**
     * Refuses a declaration outside the bounds every release shares; the empty BIT width 5.6 accepts stays a release rule.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the declaration exceeds a shared bound
     */
    public function invariant(TypeName $type): void
    {
        $problem = $this->problem($type, '', \SqlSemantics\Contract\GrammarRelease::MySql5651);
        \SqlSemantics\Diagnostic\Check::input($problem === null, $problem === null ? '' : $problem->message());
    }
}
