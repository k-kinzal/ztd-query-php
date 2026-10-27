<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NestedColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns>
 */
final class NestedColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral> $path Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $path, ErisGenerator $columns, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns(path: $parts[0], columns: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $path,
            $columns,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
