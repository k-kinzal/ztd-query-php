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
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\SpatialAlias|null>|null $alias Generated type keyword
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind($kind, static function (\SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind $selected) use ($inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Spatial {
                return new \SqlSemantics\Platform\MySql\Statement\Type\Spatial(kind: $parts[0], inputSource: $parts[1], alias: $parts[2]);
            }, \Tests\Generator\Operands::tuple(
                Generators::constant($selected),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\SpatialAliasGenerator::forKind($selected)
            ));
        });
    }
}
