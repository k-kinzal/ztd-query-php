<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RedoLogSwitch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch>
 */
final class RedoLogSwitchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $enable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $engine Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $log Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $enable, ErisGenerator $engine, ErisGenerator $log, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch(enable: $parts[0], engine: $parts[1], log: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $enable,
            $engine,
            $log,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
