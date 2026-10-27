<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Spatial values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Spatial>
 */
final class SpatialGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Spatial> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Spatial {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Spatial(kind: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
