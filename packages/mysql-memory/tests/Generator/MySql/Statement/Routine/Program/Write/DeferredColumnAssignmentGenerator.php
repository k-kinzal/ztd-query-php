<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredColumnAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>
 */
final class DeferredColumnAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment(column: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
