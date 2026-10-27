<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FactorStep values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep>
 */
final class FactorStepGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\Factor> $factor Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\Identification|null>|null $identification Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $factor, ?ErisGenerator $identification = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep {
            return new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(factor: $parts[0], identification: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $factor,
            $identification ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
