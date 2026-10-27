<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AddColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn>
 */
final class AddColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition|null>|null $position Generated constructor operand
     * @param ErisGenerator<bool>|null $columnWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ?ErisGenerator $position = null, ?ErisGenerator $columnWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn(column: $parts[0], position: $parts[1], columnWritten: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $position ?? Generators::constant(null),
            $columnWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
