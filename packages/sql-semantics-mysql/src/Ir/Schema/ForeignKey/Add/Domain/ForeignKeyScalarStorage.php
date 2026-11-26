<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved scalar field with its effective native dimensions.
 * @visibility public
 * @example Measuring a packed decimal field
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind::Decimal;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage($kind, 10, 2))->keyBytes() // => 5
 */
final class ForeignKeyScalarStorage
{
    use Snapshot;

    /**
     * Checks the dimensions and attributes specific to the effective encoding.
     */
    public function __construct(
        public readonly ForeignKeyScalarKind $kind,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $unsigned = false,
        public readonly bool $zerofill = false,
    ) {
        [$minimum, $maximum, $numeric, $optional] = $kind->dimensions();
        Check::input((!$unsigned || $numeric) && (!$zerofill || $unsigned), 'Native scalar numeric attributes belong to an eligible unsigned role.');
        if ($minimum === null || ($optional && $precision === null)) {
            $dimensions = $precision === null && $scale === null;
        } else {
            $width = $precision !== null && $precision >= $minimum && $precision <= $maximum;
            $places = $numeric ? ($scale !== null && $scale >= 0 && $scale <= min(30, $precision ?? 0)) : $scale === null;
            $dimensions = $width && $places;
        }
        Check::input($dimensions, 'The native scalar field dimensions must fit the selected encoding.');
    }

    /**
     * Reports the width of the field in a full native key.
     */
    public function keyBytes(): int
    {
        $precision = $this->precision ?? 0;
        $scale = $this->scale ?? 0;
        $fraction = intdiv($precision + 1, 2);
        $decimal = 0;
        foreach ([$precision - $scale, $scale] as $digits) {
            $decimal += intdiv($digits, 9) * 4 + intdiv(($digits % 9) + 1, 2);
        }
        return match ($this->kind) {
            ForeignKeyScalarKind::Decimal => $decimal,
            ForeignKeyScalarKind::Bit => intdiv($precision + 7, 8),
            ForeignKeyScalarKind::Date => 3,
            ForeignKeyScalarKind::Year => 1,
            ForeignKeyScalarKind::Time => 3 + $fraction,
            ForeignKeyScalarKind::DateTime => 5 + $fraction,
            ForeignKeyScalarKind::Timestamp => 4 + $fraction,
            ForeignKeyScalarKind::Single => 4,
            ForeignKeyScalarKind::Double => 8,
        };
    }

}
