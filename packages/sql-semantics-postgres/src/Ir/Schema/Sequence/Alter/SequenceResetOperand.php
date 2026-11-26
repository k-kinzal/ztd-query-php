<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

/**
 * Use the native operand of a bound reset or an implicit restart.
 * @visibility public
 * @example Use the effective restart value
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand::Native->value // => 'native-default'
 */
enum SequenceResetOperand: string
{
    case Native = 'native-default';
}
