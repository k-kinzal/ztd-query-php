<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json\Path;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Index values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index>
 */
final class IndexGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $last Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $last, ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index(last: $parts[0], offset: $parts[1]);
        }, Generators::tuple(
            $last,
            $offset
        ));
    }
}
