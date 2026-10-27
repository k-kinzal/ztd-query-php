<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Branching;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CaseBranch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch>
 */
final class CaseBranchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $condition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $result Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $condition, ErisGenerator $result, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(condition: $parts[0], result: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $condition,
            $result,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
