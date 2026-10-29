<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Path values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path>
 */
final class PathGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg>> $legs Generated constructor operand
     */
    public function __construct(ErisGenerator $legs)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\Path {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path(legs: $parts[0]);
        }, Generators::tuple(
            $legs
        ));
    }
}
