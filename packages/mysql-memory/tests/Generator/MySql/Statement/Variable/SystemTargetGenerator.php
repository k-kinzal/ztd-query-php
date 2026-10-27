<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget>
 */
final class SystemTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null>|null $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $instance Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $scope = null, ?ErisGenerator $instance = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget(name: $parts[0], scope: $parts[1], instance: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $scope ?? Generators::constant(null),
            $instance ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
