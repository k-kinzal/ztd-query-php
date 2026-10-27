<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ChangeColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn>
 */
final class ChangeColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName|null> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition> $definition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition|null>|null $position Generated constructor operand
     * @param ErisGenerator<bool>|null $columnWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $definition, ?ErisGenerator $position = null, ?ErisGenerator $columnWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn(column: $parts[0], definition: $parts[1], position: $parts[2], columnWritten: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $definition,
            $position ?? Generators::constant(null),
            $columnWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
