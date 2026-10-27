<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExecutionTimeHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint>
 */
final class ExecutionTimeHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint> */
    use Composition;

    /**
     * @param ErisGenerator<string> $milliseconds Generated constructor operand
     */
    public function __construct(ErisGenerator $milliseconds)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint(milliseconds: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $milliseconds
        ));
    }
}
