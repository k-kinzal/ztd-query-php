<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json\Path;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Leg values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg>
 */
final class LegGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index|null>|null $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index|null>|null $to Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $name = null, ?ErisGenerator $from = null, ?ErisGenerator $to = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg(kind: $parts[0], name: $parts[1], from: $parts[2], to: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $name ?? Generators::constant(null),
            $from ?? Generators::constant(null),
            $to ?? Generators::constant(null)
        ));
    }
}
