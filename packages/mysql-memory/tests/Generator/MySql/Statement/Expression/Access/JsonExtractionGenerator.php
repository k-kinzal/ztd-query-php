<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Access;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes JsonExtraction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction>
 */
final class JsonExtractionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $path Generated constructor operand
     * @param ErisGenerator<bool>|null $unquote Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $path, ?ErisGenerator $unquote = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction(column: $parts[0], path: $parts[1], unquote: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $path,
            $unquote ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
