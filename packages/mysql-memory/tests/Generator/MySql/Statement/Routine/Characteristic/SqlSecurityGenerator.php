<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Characteristic;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SqlSecurity values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity>
 */
final class SqlSecurityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext> $context Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $context, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity(context: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $context,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
