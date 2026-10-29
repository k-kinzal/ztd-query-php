<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Prepared;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SyntaxRefusal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal>
 */
final class SyntaxRefusalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<int> $line Generated constructor operand
     * @param ErisGenerator<string> $near Generated constructor operand
     */
    public function __construct(ErisGenerator $offset, ErisGenerator $line, ErisGenerator $near)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(offset: $parts[0], line: $parts[1], near: $parts[2]);
        }, Generators::tuple(
            $offset,
            $line,
            $near
        ));
    }
}
