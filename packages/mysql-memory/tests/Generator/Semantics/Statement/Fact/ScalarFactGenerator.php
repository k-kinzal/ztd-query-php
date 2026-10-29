<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ScalarFact values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact>
 */
final class ScalarFactGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\ScalarFact> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Type\TypeFact> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Type\Nullability> $nullability Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Column\Resolution|null>|null $resolution Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $replacement Generated constructor operand
     */
    public function __construct(ErisGenerator $type, ErisGenerator $nullability, ?ErisGenerator $resolution = null, ?ErisGenerator $replacement = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Fact\ScalarFact {
            return new \SqlSemantics\Statement\Fact\ScalarFact(type: $parts[0], nullability: $parts[1], resolution: $parts[2], replacement: $parts[3]);
        }, Generators::tuple(
            $type,
            $nullability,
            $resolution ?? Generators::constant(null),
            $replacement ?? Generators::constant(null)
        ));
    }
}
