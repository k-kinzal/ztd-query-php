<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Modifier;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LockOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption>
 */
final class LockOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     */
    public function __construct(ErisGenerator $lock, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption(lock: $parts[0], inputSource: $parts[1], operator: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $lock,
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional()
        ));
    }
}
