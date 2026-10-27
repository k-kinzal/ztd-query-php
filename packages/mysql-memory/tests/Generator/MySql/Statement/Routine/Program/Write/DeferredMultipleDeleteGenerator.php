<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredMultipleDelete values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete>
 */
final class DeferredMultipleDeleteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput> $input Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm> $form Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $options, ErisGenerator $input, ErisGenerator $form, ?ErisGenerator $where = null, ?ErisGenerator $hints = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete(with: $parts[0], options: $parts[1], input: $parts[2], form: $parts[3], where: $parts[4], hints: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $options,
            $input,
            $form,
            $where ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
