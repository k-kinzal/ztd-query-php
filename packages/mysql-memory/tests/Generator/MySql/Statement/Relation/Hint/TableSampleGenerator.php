<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation\Hint;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableSample values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample>
 */
final class TableSampleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod> $method Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $percentage Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $method, ErisGenerator $percentage, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample {
            return new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample(method: $parts[0], percentage: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $method,
            $percentage,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
