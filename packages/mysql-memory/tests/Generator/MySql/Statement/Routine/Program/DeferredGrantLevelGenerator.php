<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredGrantLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel>
 */
final class DeferredGrantLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest> $request Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $request, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel(request: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $request,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
