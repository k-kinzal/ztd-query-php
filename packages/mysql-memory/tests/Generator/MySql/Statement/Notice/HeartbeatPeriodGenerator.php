<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HeartbeatPeriod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod>
 */
final class HeartbeatPeriodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition> $condition Generated constructor operand
     */
    public function __construct(ErisGenerator $condition)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod(condition: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $condition
        ));
    }
}
