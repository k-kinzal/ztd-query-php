<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment>
 */
final class UserAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable> $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $variable, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment(variable: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $variable,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
