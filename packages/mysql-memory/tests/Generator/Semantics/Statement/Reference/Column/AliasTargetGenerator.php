<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AliasTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\AliasTarget>
 */
final class AliasTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\AliasTarget> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\Field> $field Generated constructor operand
     * @param ErisGenerator<int>|null $depth Generated constructor operand
     */
    public function __construct(ErisGenerator $field, ?ErisGenerator $depth = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Column\AliasTarget {
            return new \SqlSemantics\Statement\Reference\Column\AliasTarget(field: $parts[0], depth: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $field,
            $depth ?? Generators::constant(0)
        ));
    }
}
