<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment>
 */
final class DeferredAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable> $target Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null>|null $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $qualifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $target, ErisGenerator $value, ?ErisGenerator $scope = null, ?ErisGenerator $qualifier = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment(target: $parts[0], value: $parts[1], scope: $parts[2], qualifier: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $target,
            $value,
            $scope ?? Generators::constant(null),
            $qualifier ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
