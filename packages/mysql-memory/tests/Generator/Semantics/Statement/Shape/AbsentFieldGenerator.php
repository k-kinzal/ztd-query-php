<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AbsentField values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\AbsentField>
 */
final class AbsentFieldGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\AbsentField> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\AbsentField {
            return new \SqlSemantics\Statement\Shape\AbsentField(name: $parts[0]);
        }, Generators::tuple(
            $name
        ));
    }
}
