<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredPurge values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge>
 */
final class DeferredPurgeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $moment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $moment, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge(moment: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $moment,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
