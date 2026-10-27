<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StringFamily values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\StringFamily>
 */
final class StringFamilyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\StringFamily> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $binary Generated constructor operand
     * @param ErisGenerator<bool>|null $national Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute|null>|null $charset Generated constructor operand
     */
    public function __construct(?ErisGenerator $binary = null, ?ErisGenerator $national = null, ?ErisGenerator $charset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\StringFamily {
            return new \SqlSemantics\Platform\MySql\Statement\Type\StringFamily(binary: $parts[0], national: $parts[1], charset: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $binary ?? Generators::constant(false),
            $national ?? Generators::constant(false),
            $charset ?? Generators::constant(null)
        ));
    }
}
