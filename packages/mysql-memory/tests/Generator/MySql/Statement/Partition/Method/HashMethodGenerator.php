<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HashMethod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod>
 */
final class HashMethodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $linear Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $linear, ErisGenerator $expression, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(linear: $parts[0], expression: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $linear,
            $expression,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
