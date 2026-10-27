<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ThreadPriority values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority>
 */
final class ThreadPriorityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $negative Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $negative, ErisGenerator $number, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority {
            return new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(negative: $parts[0], number: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $negative,
            $number,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
