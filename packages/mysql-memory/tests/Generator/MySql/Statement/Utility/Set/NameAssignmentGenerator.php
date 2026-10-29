<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NameAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment>
 */
final class NameAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName|\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null>|null $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $qualifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator>|null $operator Generated assignment sign
     */
    public function __construct(ErisGenerator $name, ErisGenerator $value, ?ErisGenerator $scope = null, ?ErisGenerator $qualifier = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment(name: $parts[0], value: $parts[1], scope: $parts[2], qualifier: $parts[3], inputSource: $parts[4], operator: $parts[5]);
        }, Generators::tuple(
            $name,
            $value,
            $scope ?? Generators::constant(null),
            $qualifier ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $operator ?? new \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator()
        ));
    }
}
