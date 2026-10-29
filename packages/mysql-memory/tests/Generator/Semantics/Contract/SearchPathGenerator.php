<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SearchPath values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\SearchPath>
 */
final class SearchPathGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\SearchPath> */
    use Composition;

    /**
     * @param ErisGenerator<string> $schemas Generated constructor operand
     */
    public function __construct(ErisGenerator $schemas)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Contract\SearchPath {
            return new \SqlSemantics\Contract\SearchPath(schemas: $parts[0]);
        }, Generators::tuple(
            $schemas
        ));
    }
}
