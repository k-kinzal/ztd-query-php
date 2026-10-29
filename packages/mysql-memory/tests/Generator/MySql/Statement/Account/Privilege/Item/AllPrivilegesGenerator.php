<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Item;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AllPrivileges values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges>
 */
final class AllPrivilegesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $privileges The generated written alternative
     */
    public function __construct(?ErisGenerator $inputSource = null, ?ErisGenerator $privileges = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges(inputSource: $parts[0], privileges: $parts[1]);
        }, Generators::tuple(
            $inputSource ?? Generators::constant(null),
            $privileges ?? Generators::bool()
        ));
    }
}
