<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Temporal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Temporal>
 */
final class TemporalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Temporal> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $precision Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier>>|null $modifiers Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $precision = null, ?ErisGenerator $modifiers = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Temporal {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Temporal(kind: $parts[0], precision: $parts[1], modifiers: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $precision ?? Generators::constant(null),
            $modifiers ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
