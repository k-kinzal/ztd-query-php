<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredInsertRows values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows>
 */
final class DeferredInsertRowsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> $into Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow>> $rows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias|null>|null $alias Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $onDuplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword>|null $keyword Generated statement keyword
     */
    public function __construct(ErisGenerator $into, ErisGenerator $rows, ?ErisGenerator $alias = null, ?ErisGenerator $onDuplicate = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $keyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows(into: $parts[0], rows: $parts[1], alias: $parts[2], onDuplicate: $parts[3], inputSource: $parts[4], keyword: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $into,
            $rows,
            $alias ?? Generators::constant(null),
            $onDuplicate ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null),
            $keyword ?? new \Tests\Generator\MySql\Statement\Dml\Insert\ValuesKeywordGenerator()
        ));
    }
}
