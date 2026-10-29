<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Deprecation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation>
 */
final class DeprecationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated> $construct Generated constructor operand
     * @param ErisGenerator<string>|null $text Generated constructor operand
     * @param ErisGenerator<bool>|null $aborts Generated constructor operand
     */
    public function __construct(ErisGenerator $construct, ?ErisGenerator $text = null, ?ErisGenerator $aborts = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(construct: $parts[0], text: $parts[1], aborts: $parts[2]);
        }, Generators::tuple(
            $construct,
            $text ?? Generators::constant(''),
            $aborts ?? Generators::constant(false)
        ));
    }
}
