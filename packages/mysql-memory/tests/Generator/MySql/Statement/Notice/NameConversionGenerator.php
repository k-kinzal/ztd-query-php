<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NameConversion values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion>
 */
final class NameConversionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<bool>|null $legacy Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $legacy = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(name: $parts[0], legacy: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $legacy ?? Generators::constant(false)
        ));
    }
}
