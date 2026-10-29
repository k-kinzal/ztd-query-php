<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConditionDeclaration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration>
 */
final class ConditionDeclarationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration(name: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $name,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
