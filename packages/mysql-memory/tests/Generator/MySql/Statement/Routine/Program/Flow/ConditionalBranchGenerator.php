<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConditionalBranch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch>
 */
final class ConditionalBranchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $condition Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement>> $statements Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $condition, ErisGenerator $statements, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch(condition: $parts[0], statements: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $condition,
            $statements,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
