<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredMethod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod>
 */
final class DeferredMethodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind|null>|null $kind Generated constructor operand
     * @param ErisGenerator<bool>|null $linear Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $kind = null, ?ErisGenerator $linear = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod(expression: $parts[0], kind: $parts[1], linear: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $expression,
            $kind ?? Generators::constant(null),
            $linear ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
