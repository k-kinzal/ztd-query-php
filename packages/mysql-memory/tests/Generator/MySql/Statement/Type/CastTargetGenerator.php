<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CastTarget values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget>
 */
final class CastTargetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $length Generated constructor operand
     * @param ErisGenerator<string|null>|null $scale Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $words Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $scale = null, ?ErisGenerator $charset = null, ?ErisGenerator $words = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\CastTarget {
            return new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(kind: $parts[0], length: $parts[1], scale: $parts[2], charset: $parts[3], words: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $length ?? Generators::constant(null),
            $scale ?? Generators::constant(null),
            $charset ?? Generators::constant(null),
            $words ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
