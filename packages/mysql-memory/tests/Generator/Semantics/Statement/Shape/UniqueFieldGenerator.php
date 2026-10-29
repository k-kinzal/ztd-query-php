<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UniqueField values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\UniqueField>
 */
final class UniqueFieldGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\UniqueField> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\Field> $field Generated constructor operand
     */
    public function __construct(ErisGenerator $field)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\UniqueField {
            return new \SqlSemantics\Statement\Shape\UniqueField(field: $parts[0]);
        }, Generators::tuple(
            $field
        ));
    }
}
