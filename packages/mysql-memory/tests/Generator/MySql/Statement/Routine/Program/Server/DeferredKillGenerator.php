<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredKill values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill>
 */
final class DeferredKillGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope|null> $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $process Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $scope, ErisGenerator $process, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill(scope: $parts[0], process: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $scope,
            $process,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
