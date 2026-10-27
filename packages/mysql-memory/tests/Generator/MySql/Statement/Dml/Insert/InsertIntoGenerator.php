<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InsertInto values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto>
 */
final class InsertIntoGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $replace Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority|null> $priority Generated constructor operand
     * @param ErisGenerator<bool> $ignore Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList|null>|null $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<bool>|null $intoWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $replace, ErisGenerator $priority, ErisGenerator $ignore, ErisGenerator $table, ?ErisGenerator $columns = null, ?ErisGenerator $hints = null, ?ErisGenerator $intoWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(replace: $parts[0], priority: $parts[1], ignore: $parts[2], table: $parts[3], columns: $parts[4], hints: $parts[5], intoWritten: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $replace,
            $priority,
            $ignore,
            $table,
            $columns ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $intoWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
