<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Fields values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\Fields>
 */
final class FieldsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\Fields> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Shape\Field>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Comparison> $names Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ErisGenerator $names)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\Fields {
            return new \SqlSemantics\Statement\Shape\Fields(items: $parts[0], names: $parts[1]);
        }, Generators::tuple(
            $items,
            $names
        ));
    }
}
