<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceIntegerOrder;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the bounds decided by the assignments and spells bound changes.
 * @visibility SqlSemantics
 */
final class SequenceChangeRules
{
    /**
     * Verifies explicit limits and all positions determined by the declared operands.
     */
    public static function checkLimits(
        ?IntegerStorage $storage,
        ?WideIntegerLiteral $increment,
        WideIntegerLiteral|SequenceResetOperand|null $minimum,
        WideIntegerLiteral|SequenceResetOperand|null $maximum,
        ?WideIntegerLiteral $start,
        WideIntegerLiteral|SequenceResetOperand|null $restart,
    ): void {
        [$floor, $ceiling] = SequenceIntegerOrder::limits($storage ?? IntegerStorage::Wide);
        $ascending = $increment === null ? null : SequenceIntegerOrder::compare($increment, new WideIntegerLiteral('0')) > 0;
        $lower = $minimum instanceof WideIntegerLiteral ? $minimum : $floor;
        if ($minimum === SequenceResetOperand::Native && $ascending === true && $storage === null) {
            $lower = new WideIntegerLiteral('1');
        }
        $upper = $maximum instanceof WideIntegerLiteral ? $maximum : $ceiling;
        if ($maximum === SequenceResetOperand::Native && $ascending === false && $storage === null) {
            $upper = new WideIntegerLiteral('-1');
        }
        Check::input(
            SequenceIntegerOrder::compare($floor, $lower) <= 0 && SequenceIntegerOrder::compare($upper, $ceiling) <= 0
                && SequenceIntegerOrder::compare($lower, $upper) < 0,
            'Sequence limits must increase within the selected integer storage.'
        );
        foreach ([$start, $restart] as $position) {
            Check::input(
                !$position instanceof WideIntegerLiteral
                    || (SequenceIntegerOrder::compare($lower, $position) <= 0 && SequenceIntegerOrder::compare($position, $upper) <= 0),
                'The sequence start and restart must be within its limits.'
            );
        }
    }

    /**
     * Emits one optional bound from its checked number or reset role.
     */
    public static function bound(string $keyword, WideIntegerLiteral|SequenceResetOperand|null $operand): string
    {
        return match (true) {
            $operand instanceof WideIntegerLiteral => ' ' . $keyword . ' ' . $operand->decimal,
            $operand === SequenceResetOperand::Native => ' NO ' . $keyword,
            $operand === null => '',
        };
    }
}
