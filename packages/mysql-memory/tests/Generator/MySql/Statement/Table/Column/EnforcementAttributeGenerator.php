<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EnforcementAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute>
 */
final class EnforcementAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $enforced Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $enforced, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute(enforced: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $enforced,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
