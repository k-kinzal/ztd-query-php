<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Trigger;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TriggerOrder values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder>
 */
final class TriggerOrderGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement> $placement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $other Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $placement, ErisGenerator $other, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder(placement: $parts[0], other: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $placement,
            $other,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
