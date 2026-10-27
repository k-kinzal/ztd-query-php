<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Loop values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop>
 */
final class LoopGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement>> $statements Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $label Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $endLabel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $statements, ?ErisGenerator $label = null, ?ErisGenerator $endLabel = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop(statements: $parts[0], label: $parts[1], endLabel: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $statements,
            $label ?? Generators::constant(null),
            $endLabel ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
