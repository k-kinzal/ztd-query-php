<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredInsertSet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet>
 */
final class DeferredInsertSetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> $into Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>> $assignments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias|null>|null $alias Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $onDuplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $into, ErisGenerator $assignments, ?ErisGenerator $alias = null, ?ErisGenerator $onDuplicate = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet(into: $parts[0], assignments: $parts[1], alias: $parts[2], onDuplicate: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $into,
            $assignments,
            $alias ?? Generators::constant(null),
            $onDuplicate ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
