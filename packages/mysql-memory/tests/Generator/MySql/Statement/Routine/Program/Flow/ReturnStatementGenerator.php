<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReturnStatement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement>
 */
final class ReturnStatementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(value: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
