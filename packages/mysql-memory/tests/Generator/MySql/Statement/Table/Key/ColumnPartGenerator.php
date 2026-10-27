<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ColumnPart values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart>
 */
final class ColumnPartGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $length Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Direction|null>|null $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ?ErisGenerator $length = null, ?ErisGenerator $direction = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart(column: $parts[0], length: $parts[1], direction: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $length ?? Generators::constant(null),
            $direction ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
