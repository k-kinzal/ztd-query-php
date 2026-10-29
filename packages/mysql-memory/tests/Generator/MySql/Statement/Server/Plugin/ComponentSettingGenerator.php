<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ComponentSetting values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting>
 */
final class ComponentSettingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget> $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName|\SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator>|null $operator Generated assignment sign
     */
    public function __construct(ErisGenerator $variable, ErisGenerator $value, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting(variable: $parts[0], value: $parts[1], inputSource: $parts[2], operator: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $variable,
            $value,
            $inputSource ?? Generators::constant(null),
            $operator ?? new \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator()
        ));
    }
}
