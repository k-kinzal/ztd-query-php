<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ToggleKeys values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys>
 */
final class ToggleKeysGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $enable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $enable, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys(enable: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $enable,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
