<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Charset values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset>
 */
final class CharsetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<int> $maxLength Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $maxLength)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset(name: $parts[0], maxLength: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $maxLength
        ));
    }
}
