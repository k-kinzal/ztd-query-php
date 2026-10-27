<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TrimmedName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName>
 */
final class TrimmedNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
}
