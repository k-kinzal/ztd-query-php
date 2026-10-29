<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Integral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Integral>
 */
final class IntegralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Integral> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $width Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier>>|null $modifiers Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias|null>|null $alias Generated type keyword
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $width = null, ?ErisGenerator $modifiers = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind($kind, static function (\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind $selected) use ($width, $modifiers, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Integral {
                return new \SqlSemantics\Platform\MySql\Statement\Type\Integral(kind: $parts[0], width: $parts[1], modifiers: $parts[2], inputSource: $parts[3], alias: $parts[4]);
            }, Generators::tuple(
                Generators::constant($selected),
                $width ?? Generators::constant(null),
                $modifiers ?? Generators::constant([]),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\IntegralAliasGenerator::forKind($selected)
            ));
        });
    }
}
