<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ColumnVisibility values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility>
 */
final class ColumnVisibilityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName> $column Generated constructor operand
     * @param ErisGenerator<bool> $visible Generated constructor operand
     * @param ErisGenerator<bool>|null $columnWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $visible, ?ErisGenerator $columnWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility(column: $parts[0], visible: $parts[1], columnWritten: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $visible,
            $columnWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
