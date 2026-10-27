<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OpenStar values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\OpenStar>
 */
final class OpenStarGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\OpenStar> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\OpenStar {
            return new \SqlSemantics\Statement\Shape\OpenStar(missing: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $missing
        ));
    }
}
