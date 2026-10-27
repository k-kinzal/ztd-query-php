<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredEvaluation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation>
 */
final class DeferredEvaluationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation(items: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
