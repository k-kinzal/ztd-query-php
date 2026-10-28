<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredCast values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast>
 */
final class DeferredCastGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget> $target Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $array The generated ARRAY clause
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm>|null $form The written cast form
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $target, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $array = null, ?ErisGenerator $form = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast(operand: $parts[0], target: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3], array: $parts[4], form: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $target,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $array ?? Generators::constant(false),
            $form ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm::Cast)
        ));
    }
}
