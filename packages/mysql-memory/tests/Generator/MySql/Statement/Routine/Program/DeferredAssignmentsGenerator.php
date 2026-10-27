<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredAssignments values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments>
 */
final class DeferredAssignmentsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments(items: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
