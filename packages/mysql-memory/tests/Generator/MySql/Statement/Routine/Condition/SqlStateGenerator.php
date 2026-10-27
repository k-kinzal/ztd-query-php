<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SqlState values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState>
 */
final class SqlStateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $state Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $state, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState(state: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $state,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
