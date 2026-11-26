<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

/**
 * Resolves native scalar field encodings from unambiguous declarations.
 * @visibility SqlSemantics
 */
final class ForeignKeyScalarLowering
{
    /**
     * Classifies the effective encoding, independent of aliases and default spelling.
     */
    public function storage(TypeDescriptor $type, GrammarRelease $release): ?ForeignKeyScalarStorage
    {
        if ($type instanceof Decimal) {
            $precision = $this->dimension($type->precision);
            $scale = $this->dimension($type->scale) ?? 0;
            [$unsigned, $zerofill] = $this->flags($type->modifiers);
            return new ForeignKeyScalarStorage(
                ForeignKeyScalarKind::Decimal,
                $precision === null || ($precision === 0 && $scale === 0) ? 10 : $precision,
                $scale,
                $unsigned,
                $zerofill
            );
        }
        if ($type instanceof Elementary && $type->kind === ElementaryKind::Bit) {
            $bits = $this->dimension($type->length) ?? 1;
            return new ForeignKeyScalarStorage(
                ForeignKeyScalarKind::Bit,
                $bits === 0 && $release === GrammarRelease::MySql5651 ? 1 : $bits
            );
        }
        if ($type instanceof Temporal) {
            return $this->temporal($type, $release);
        }

        return $type instanceof Floating ? $this->approximate($type) : null;
    }

    /**
     * Resolves temporal storage using the stock on-disk formats of the language release.
     */
    public function temporal(Temporal $type, GrammarRelease $release): ForeignKeyScalarStorage
    {
        $precision = $this->dimension($type->precision);
        $kind = match ($type->kind) {
            TemporalKind::Date => ForeignKeyScalarKind::Date,
            TemporalKind::Year => ForeignKeyScalarKind::Year,
            TemporalKind::Time => ForeignKeyScalarKind::Time,
            TemporalKind::DateTime => ForeignKeyScalarKind::DateTime,
            TemporalKind::Timestamp => ForeignKeyScalarKind::Timestamp,
        };
        if ($kind === ForeignKeyScalarKind::Year) {
            Check::input(
                $precision === null || $precision === 4
                    || ($release === GrammarRelease::MySql5651 && $precision <= 4294967295),
                'The native YEAR declaration requires a release-appropriate display width.'
            );
        }

        return new ForeignKeyScalarStorage($kind, $kind->packed() ? ($precision ?? 0) : null);
    }

    /**
     * Separates single and double encodings from optional display dimensions.
     */
    public function approximate(Floating $type): ForeignKeyScalarStorage
    {
        $precision = $this->dimension($type->precision);
        $scale = $this->dimension($type->scale);
        $kind = match ($type->kind) {
            FloatingKind::Float => ForeignKeyScalarKind::Single,
            FloatingKind::Double, FloatingKind::Real => ForeignKeyScalarKind::Double,
        };
        if ($scale === null && $precision !== null) {
            Check::input($kind === ForeignKeyScalarKind::Single && $precision <= 53, 'Native floating precision must select a supported binary width.');
            $kind = $precision <= 24 ? ForeignKeyScalarKind::Single : ForeignKeyScalarKind::Double;
            $precision = null;
        }
        [$unsigned, $zerofill] = $this->flags($type->modifiers);

        return new ForeignKeyScalarStorage($kind, $precision, $scale, $unsigned, $zerofill);
    }

    /**
     * Resolves ZEROFILL and the associated unsigned range.
     * @param list<NumericModifier> $modifiers The parsed declaration attributes
     * @return array{bool, bool}
     */
    public function flags(array $modifiers): array
    {
        $zerofill = in_array(NumericModifier::Zerofill, $modifiers, true);

        return [$zerofill || in_array(NumericModifier::Unsigned, $modifiers, true), $zerofill];
    }

    /**
     * Reads a whole-number native dimension.
     */
    public function dimension(?string $value): ?int
    {
        Check::input($value === null || preg_match('/\A[0-9]+\z/D', $value) === 1, 'A native scalar key requires a checked integer dimension.');

        return $value === null ? null : (int) $value;
    }
}
