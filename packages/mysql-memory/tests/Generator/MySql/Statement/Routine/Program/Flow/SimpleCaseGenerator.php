<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SimpleCase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase>
 */
final class SimpleCaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $operand Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch>> $branches Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement>>|null $otherwise Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $branches, ?ErisGenerator $otherwise = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase(operand: $parts[0], branches: $parts[1], otherwise: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $operand,
            $branches,
            $otherwise ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
