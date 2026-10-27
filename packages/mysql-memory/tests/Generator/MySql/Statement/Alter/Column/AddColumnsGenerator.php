<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AddColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns>
 */
final class AddColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement>> $elements Generated constructor operand
     * @param ErisGenerator<bool>|null $columnWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $elements, ?ErisGenerator $columnWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns(elements: $parts[0], columnWritten: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $elements,
            $columnWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
