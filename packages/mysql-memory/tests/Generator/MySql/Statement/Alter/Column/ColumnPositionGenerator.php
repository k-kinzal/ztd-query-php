<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ColumnPosition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition>
 */
final class ColumnPositionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $after Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $after, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition(after: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $after,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
