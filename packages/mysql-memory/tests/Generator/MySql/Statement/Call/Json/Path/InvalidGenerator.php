<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json\Path;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Invalid values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid>
 */
final class InvalidGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid> */
    use Composition;

    /**
     * @param ErisGenerator<int> $position Generated constructor operand
     */
    public function __construct(ErisGenerator $position)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid(position: $parts[0]);
        }, Generators::tuple(
            $position
        ));
    }
}
