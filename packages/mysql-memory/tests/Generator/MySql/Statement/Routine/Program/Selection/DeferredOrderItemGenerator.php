<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Selection;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredOrderItem values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>
 */
final class DeferredOrderItemGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Direction|null>|null $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $direction = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem(expression: $parts[0], direction: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $expression,
            $direction ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
