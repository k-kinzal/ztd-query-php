<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes VariableHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint>
 */
final class VariableHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint> */
    use Composition;

    /**
     * @param ErisGenerator<string> $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral> $value Generated constructor operand
     */
    public function __construct(ErisGenerator $variable, ErisGenerator $value)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint(variable: $parts[0], value: $parts[1]);
        }, Generators::tuple(
            $variable,
            $value
        ));
    }
}
