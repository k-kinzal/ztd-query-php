<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RowShape values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\RowShape>
 */
final class RowShapeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\RowShape> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Shape\OutputSlot>> $slots Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>>|null $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $slots, ?ErisGenerator $missing = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\RowShape {
            return new \SqlSemantics\Statement\Shape\RowShape(slots: $parts[0], missing: $parts[1]);
        }, Generators::tuple(
            $slots,
            $missing ?? Generators::constant([])
        ));
    }
}
