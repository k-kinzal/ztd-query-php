<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrantOptionRight values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight>
 */
final class GrantOptionRightGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight(inputSource: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $inputSource ?? Generators::constant(null)
        ));
    }
}
